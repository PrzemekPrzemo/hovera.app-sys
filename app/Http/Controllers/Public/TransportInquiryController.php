<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Mail\Customer\TransportLeadAccessMail;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Publiczny formularz zapytania o transport koni. Bez auth — każdy może
 * wypełnić, my tworzymy `transport_lead` w status=open. LeadDispatcher
 * (faza 5+6 krok 4) podejmuje to dispatch'em do transporterów.
 *
 * Routes:
 *   GET  /transport/zapytanie          → formularz
 *   POST /transport/zapytanie          → submit + redirect na dziekujemy
 *   GET  /transport/zapytanie/dziekujemy/{lead}  → potwierdzenie
 *
 * Tryby:
 *   • Bez query → mode=broadcast, dispatcher rozsyła do wszystkich pasujących
 *     transporterów w voivodeship (+ adjacent).
 *   • `?transporter={slug}` (z CTA na /t/{slug}) → mode=direct, lead targetuje
 *     tylko tego konkretnego transportera. Jeśli slug nie rozwiązuje się do
 *     verified+aktywnego transportera, po cichu fallback na broadcast — nie
 *     chcemy 404'ować formularza tylko dlatego, że ktoś podstrzelił URL.
 *
 * Throttle POST 5/h z IP (anti-spam, mocniej niż signup bo lead = mail
 * leci do transporterów).
 */
class TransportInquiryController extends Controller
{
    public function show(Request $request): View
    {
        $targetTransporter = $this->resolveTransporterFromRequest($request);
        $originatorStable = $this->resolveOriginatorStable($request);
        $horseContext = $this->resolveHorseContext($request, $originatorStable);

        // Defaulty pre-fillu — query params (?stable=…&horse=…) wygrywają
        // nad sesją (old). Jeżeli user editował już raz formularz, jego
        // wpisy nadpisują nasze pre-fill'e (old() ma pierwszeństwo) —
        // tylko pierwszy load z /app dostaje hint'y.
        $defaults = $this->buildPrefillDefaults($originatorStable, $horseContext);

        // Boarder picker — pokazujemy gdy stable jest originator'em. Lista
        // aktywnych boardings z horse_boarding_assignments (PR 4/5 foundation).
        // Stable może zaznaczyć "transport dla boarder'a X" → lead leci z
        // client_type='owner', FV po acceptacji idzie do owner'a, nie stajni.
        $boarders = $originatorStable !== null
            ? $this->resolveActiveBoarders($originatorStable)
            : [];

        return view('public.transport.inquiry', [
            'old' => [
                'customer_name' => (string) old('customer_name', $defaults['customer_name']),
                'customer_email' => (string) old('customer_email', $defaults['customer_email']),
                'customer_phone' => (string) old('customer_phone'),
                'pickup_address' => (string) old('pickup_address', $defaults['pickup_address']),
                'dropoff_address' => (string) old('dropoff_address'),
                'preferred_date' => (string) old('preferred_date', now()->addDays(7)->toDateString()),
                'preferred_time' => (string) old('preferred_time'),
                'horse_count' => (int) old('horse_count', $defaults['horse_count']),
                'notes' => (string) old('notes', $defaults['notes']),
                'client_for' => (string) old('client_for', 'stable'),
            ],
            'targetTransporter' => $targetTransporter,
            'originatorStable' => $originatorStable,
            'boarders' => $boarders,
        ]);
    }

    /**
     * Aktywne boarding assignments dla stajni — lista par
     * (assignment_id, label) gotowa do dropdownu w formie.
     * Label = "Imię konia — owner_name".
     *
     * @return list<array{id: string, label: string, owner_user_id: ?string}>
     */
    private function resolveActiveBoarders(Tenant $stable): array
    {
        $assignments = HorseBoardingAssignment::query()
            ->with(['horse', 'owner'])
            ->where('stable_tenant_id', $stable->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->get();

        return $assignments->map(function (HorseBoardingAssignment $a) {
            $horseName = (string) ($a->horse?->name ?? __('public/transport_inquiry.boarder.unknown_horse'));
            $ownerName = (string) ($a->owner?->name ?? __('public/transport_inquiry.boarder.unknown_owner'));

            return [
                'id' => (string) $a->id,
                'label' => $horseName.' — '.$ownerName,
                'owner_user_id' => $a->owner_user_id !== null ? (string) $a->owner_user_id : null,
            ];
        })->all();
    }

    public function submit(Request $request, MapboxGeocoder $geocoder): RedirectResponse
    {
        // Honeypot — boty wypełniają wszystkie pola po kolei, włącznie z
        // ukrytym `website` (display:none + tabindex=-1). Bona-fide klient
        // nigdy nie ma tej wartości. Silent 200 redirect żeby spam tooling nie
        // wykrył że został wycięty + nie tracimy zasobów na geocoding.
        if ((string) $request->input('website', '') !== '') {
            return back();
        }

        $data = $this->validate($request);

        try {
            $from = $geocoder->geocode($data['pickup_address']);
            $to = $geocoder->geocode($data['dropoff_address']);
        } catch (GeocodingException $e) {
            return back()
                ->withErrors(['address' => __('public/transport_inquiry.error.geocoding', ['msg' => $e->getMessage()])])
                ->withInput();
        }

        $targetTransporter = $this->resolveTransporterFromRequest($request);
        $isDirect = $targetTransporter !== null;
        $originatorStable = $this->resolveOriginatorStable($request);

        // Klient zlecenia — wybór "Stajnia (ja)" vs "Boarder: X". Default
        // = anonymous (lead z publicznego formu bez stable context'a).
        // Patrz docs/MARKETPLACE-ROADMAP.md PR 7.
        $clientResolution = $this->resolveClient($request, $originatorStable);

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'access_slug' => (string) Str::uuid(),
            'mode' => $isDirect ? 'direct' : 'broadcast',
            'targeted_transporter_ids' => $isDirect ? [$targetTransporter->id] : null,
            'originator_tenant_id' => $originatorStable?->id,
            'originator_user_id' => $originatorStable !== null ? Auth::id() : null,
            'originator_name' => $data['customer_name'],
            'originator_email' => $data['customer_email'],
            'originator_phone' => $data['customer_phone'] ?? null,
            'client_type' => $clientResolution['type'],
            'client_user_id' => $clientResolution['user_id'],
            'created_by_tenant_id' => $originatorStable?->id,
            'pickup_address' => $from->displayName,
            'pickup_lat' => $from->coords->lat,
            'pickup_lng' => $from->coords->lng,
            'pickup_voivodeship' => $from->voivodeship ?? '',
            'dropoff_address' => $to->displayName,
            'dropoff_lat' => $to->coords->lat,
            'dropoff_lng' => $to->coords->lng,
            'dropoff_voivodeship' => $to->voivodeship ?? '',
            'preferred_date' => $data['preferred_date'],
            'preferred_time' => $data['preferred_time'] ?? null,
            'flexible_date' => (bool) ($data['flexible_date'] ?? false),
            'horse_count' => $data['horse_count'],
            'notes' => $data['notes'] ?? null,
            'status' => 'open',
            'expires_at' => Carbon::now()->addDays((int) config('transport.leads.expires_after_days', 14)),
        ]);

        // Dispatch — sync, bo zwykle 0-30 transporterów per voivodeship; gdy
        // marketplace urośnie >50 + adjacency, zqueueujemy to przez Job.
        // Notyfikacje email lecą wewnątrz, fail któregokolwiek nie psuje
        // pozostałych (per-transporter try/catch).
        app(LeadDispatcher::class)->dispatch($lead);

        $this->sendLeadAccessMail($lead);

        return redirect()->route('public.transport.inquiry.thanks', ['lead' => $lead->id]);
    }

    /**
     * Email do klienta z permanent linkiem do portalu zapytania. Soft-fail —
     * przerwa w mailerze nie blokuje lead submit'u, klient i tak dostanie
     * thanks page i może użyć tego linka z URL (chyba że zgubił maila).
     */
    private function sendLeadAccessMail(TransportLead $lead): void
    {
        if (! $lead->originator_email || ! $lead->access_slug) {
            return;
        }

        try {
            $url = route('public.transport.lead_portal', ['slug' => $lead->access_slug]);
            Mail::to($lead->originator_email)->send(new TransportLeadAccessMail($lead, $url));
        } catch (Throwable $e) {
            Log::warning('Transport lead access mail failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function thanks(string $leadId): View
    {
        $lead = TransportLead::query()->where('id', $leadId)->firstOrFail();

        return view('public.transport.inquiry-thanks', ['lead' => $lead]);
    }

    /**
     * Resolve `?transporter={slug}` (lub hidden input z POST) do Tenant model,
     * pod warunkiem że slug istnieje, jest aktywny i przeszedł weryfikację.
     * Niespełnienie któregokolwiek warunku → null (silent fallback do broadcast).
     *
     * Cache key spójny z TransporterProfileController::resolveTenant — share'ujemy
     * te same 5-minutowe wpisy, więc kliknięcie CTA na profilu nie generuje
     * dodatkowej query.
     */
    private function resolveTransporterFromRequest(Request $request): ?Tenant
    {
        $slug = (string) $request->input('transporter', '');

        if ($slug === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        $tenant = Cache::remember(
            "public_transporter:{$slug}",
            now()->addMinutes(5),
            function () use ($slug) {
                $candidate = Tenant::query()
                    ->where('slug', $slug)
                    ->whereIn('status', ['trialing', 'active', 'past_due'])
                    ->first();

                if (! $candidate || ! $candidate->isVerifiedTransporter()) {
                    return null;
                }

                return $candidate;
            },
        );

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * Resolve `?stable={ulid}` do Tenant — ale tylko gdy zalogowany user
     * ma aktywne membership w tym tenancie. Silent null w innym przypadku
     * (gracieusna degradacja — nie 403'ujemy ani nie eksponujemy istnienia
     * stable owom anonimowym).
     *
     * Wymóg `canUseTransport()` jest poza zakresem tej metody — controller
     * publiczny dopuszcza każde stable do submita; gate jest na entry
     * pointach panelu (TransportEntry::canAccess + sidebar visibility).
     */
    private function resolveOriginatorStable(Request $request): ?Tenant
    {
        $stableId = (string) $request->input('stable', '');

        if ($stableId === '' || ! preg_match('/^[0-9A-Za-z]{26}$/', $stableId)) {
            return null;
        }

        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        // Czy user ma aktywne (non-revoked) membership w tym tenancie?
        $hasMembership = TenantMembership::query()
            ->where('tenant_id', $stableId)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasMembership) {
            return null;
        }

        return Tenant::query()->find($stableId);
    }

    /**
     * Resolve `?horse={ulid}` — wymaga aktywnego $stable bo Horse model
     * żyje per-tenant connection. Setujemy tenant na czas zapytania,
     * po czym revertujemy żeby nie zatruć reszty requestu.
     */
    private function resolveHorseContext(Request $request, ?Tenant $stable): ?Horse
    {
        $horseId = (string) $request->input('horse', '');

        if ($horseId === '' || $stable === null) {
            return null;
        }

        if (! preg_match('/^[0-9A-Za-z]{26}$/', $horseId)) {
            return null;
        }

        try {
            return app(TenantManager::class)->execute($stable, function () use ($horseId): ?Horse {
                return Horse::query()->find($horseId);
            });
        } catch (Throwable) {
            // Tenant connection nieczynny / mig nie wykonana → ignoruj.
            return null;
        }
    }

    /**
     * @return array{customer_name:string, customer_email:string, pickup_address:string, horse_count:int, notes:string}
     */
    private function buildPrefillDefaults(?Tenant $stable, ?Horse $horse): array
    {
        $defaults = [
            'customer_name' => '',
            'customer_email' => '',
            'pickup_address' => '',
            'horse_count' => 1,
            'notes' => '',
        ];

        if ($stable !== null) {
            $user = Auth::user();
            if ($user !== null) {
                $defaults['customer_name'] = (string) ($user->name ?? '');
                $defaults['customer_email'] = (string) ($user->email ?? '');
            }

            // public_profile.address ustawiany w TenantSettings — patrz
            // app/Filament/App/Pages/TenantSettings.php :: save().
            $address = (string) data_get($stable->settings ?? [], 'public_profile.address', '');
            if ($address !== '') {
                $defaults['pickup_address'] = $address;
            }
        }

        if ($horse !== null) {
            $defaults['horse_count'] = 1;
            $defaults['notes'] = __('public/transport_inquiry.prefill.horse_note', ['name' => (string) $horse->name]);
        }

        return $defaults;
    }

    /**
     * Rozwiązuje klienta zlecenia z form input'u. Trzy ścieżki:
     *
     *   - Stable submituje + wybrał "Stajnia (ja)" / brak boarder'a →
     *     client_type='stable', client_user_id=NULL (FV idzie do stajni)
     *   - Stable submituje + wybrał konkretny boarder (`boarder:{id}`):
     *     resolve'ujemy boarding_assignment, sprawdzamy że należy do tej
     *     stajni i jest active → client_type='owner', client_user_id=
     *     boarder.owner_user_id (FV idzie do owner'a)
     *   - Brak stable context'u → client_type='anonymous' (publiczny lead)
     *
     * Defensive: jeśli boarder_assignment_id z input'a NIE pasuje do
     * tej stajni lub nie jest active → graceful fallback do 'stable'
     * (nie crash'ujemy formy z błędem; user może nie wiedzieć że
     * boarding się zakończył w międzyczasie).
     *
     * @return array{type: string, user_id: ?string}
     */
    private function resolveClient(Request $request, ?Tenant $stable): array
    {
        if ($stable === null) {
            return ['type' => TransportLead::CLIENT_TYPE_ANONYMOUS, 'user_id' => null];
        }

        $clientFor = (string) $request->input('client_for', 'stable');

        if (! str_starts_with($clientFor, 'boarder:')) {
            return ['type' => TransportLead::CLIENT_TYPE_STABLE, 'user_id' => null];
        }

        $assignmentId = substr($clientFor, strlen('boarder:'));
        if (! preg_match('/^[0-9A-Za-z]{26}$/', $assignmentId)) {
            return ['type' => TransportLead::CLIENT_TYPE_STABLE, 'user_id' => null];
        }

        $assignment = HorseBoardingAssignment::query()
            ->where('id', $assignmentId)
            ->where('stable_tenant_id', $stable->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->first();

        if ($assignment === null || $assignment->owner_user_id === null) {
            // Fallback — assignment skasowany / ended / nie pasuje do
            // stajni. Zapisujemy lead jako stable (FV do stajni).
            return ['type' => TransportLead::CLIENT_TYPE_STABLE, 'user_id' => null];
        }

        return [
            'type' => TransportLead::CLIENT_TYPE_OWNER,
            'user_id' => (string) $assignment->owner_user_id,
        ];
    }

    /**
     * @return array{
     *   customer_name:string, customer_email:string, customer_phone:?string,
     *   pickup_address:string, dropoff_address:string,
     *   preferred_date:string, preferred_time:?string, flexible_date?:bool,
     *   horse_count:int, notes:?string
     * }
     */
    private function validate(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_email' => ['required', 'email:rfc,strict', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'pickup_address' => ['required', 'string', 'min:3', 'max:255'],
            'dropoff_address' => ['required', 'string', 'min:3', 'max:255'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'flexible_date' => ['nullable', 'boolean'],
            'horse_count' => ['required', 'integer', 'min:1', 'max:15'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // PR 7: stable wybiera klienta zlecenia. Format:
            //   'stable'                          → stable jest klientem (FV → stajnia)
            //   'boarder:{boarding_assignment_id}' → boarder klientem (FV → owner)
            // Walidacja w resolveClient — tu tylko sanity max length.
            'client_for' => ['nullable', 'string', 'max:64'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('public/transport_inquiry.error.terms'),
        ]);
    }
}
