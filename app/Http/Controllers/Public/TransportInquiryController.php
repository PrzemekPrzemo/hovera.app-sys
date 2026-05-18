<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
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

        return view('public.transport.inquiry', [
            'old' => [
                'customer_name' => (string) old('customer_name'),
                'customer_email' => (string) old('customer_email'),
                'customer_phone' => (string) old('customer_phone'),
                'pickup_address' => (string) old('pickup_address'),
                'dropoff_address' => (string) old('dropoff_address'),
                'preferred_date' => (string) old('preferred_date', now()->addDays(7)->toDateString()),
                'preferred_time' => (string) old('preferred_time'),
                'horse_count' => (int) old('horse_count', 1),
                'notes' => (string) old('notes'),
            ],
            'targetTransporter' => $targetTransporter,
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

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => $isDirect ? 'direct' : 'broadcast',
            'targeted_transporter_ids' => $isDirect ? [$targetTransporter->id] : null,
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
