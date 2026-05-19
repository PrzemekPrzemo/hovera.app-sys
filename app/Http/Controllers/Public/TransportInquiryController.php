<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
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
use Illuminate\Support\Str;
use Illuminate\View\View;

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
            ],
            'targetTransporter' => $targetTransporter,
            'originatorStable' => $originatorStable,
        ]);
    }

    public function submit(Request $request, MapboxGeocoder $geocoder): RedirectResponse
    {
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

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => $isDirect ? 'direct' : 'broadcast',
            'targeted_transporter_ids' => $isDirect ? [$targetTransporter->id] : null,
            'originator_tenant_id' => $originatorStable?->id,
            'originator_user_id' => $originatorStable !== null ? Auth::id() : null,
            'originator_name' => $data['customer_name'],
            'originator_email' => $data['customer_email'],
            'originator_phone' => $data['customer_phone'] ?? null,
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

        return redirect()->route('public.transport.inquiry.thanks', ['lead' => $lead->id]);
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
        } catch (\Throwable) {
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
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('public/transport_inquiry.error.terms'),
        ]);
    }
}
