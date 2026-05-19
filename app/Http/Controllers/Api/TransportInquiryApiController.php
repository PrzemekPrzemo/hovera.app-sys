<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Embed snippet — publiczne JSON API. Transporter osadza formularz na swojej
 * stronie (HTML+JS snippet z `/transport/embed-snippet`), JS posta tutaj
 * z origin'em z `tenants.embed_allowed_origins` + tokenem
 * `X-Hovera-Embed-Token`. Patrz docs/TRANSPORT.md §16.
 *
 * Bezpieczeństwo (defense-in-depth):
 *   - CORS gate przez `ResolveEmbedCors` middleware (per-tenant whitelist).
 *   - `X-Hovera-Embed-Token` weryfikowany tutaj (anti-spoofing origin).
 *   - Honeypot field `website` — boty filtrowane.
 *   - Throttle 10/h/IP (route middleware).
 *
 * Response codes:
 *   200 — `{status: 'ok', inquiry_id: '01HXY...'}`
 *   403 — wrong/missing token
 *   404 — unknown / unverified slug
 *   422 — walidacja
 *   429 — throttle
 */
class TransportInquiryApiController extends Controller
{
    public function __construct(
        private readonly MapboxGeocoder $geocoder,
        private readonly LeadDispatcher $dispatcher,
    ) {}

    public function store(Request $request): JsonResponse
    {
        // Honeypot — silent 200 (bot dostaje OK, nie wie że został wycięty).
        if ((string) $request->input('website', '') !== '') {
            return response()->json(['status' => 'ok', 'inquiry_id' => null]);
        }

        $tenant = $this->resolveTenant((string) $request->input('transporter_slug', ''));
        if ($tenant === null) {
            return response()->json([
                'status' => 'error',
                'errors' => ['transporter_slug' => ['Unknown transporter or not verified.']],
            ], 404);
        }

        if (! $this->verifyEmbedToken($request, $tenant)) {
            return response()->json([
                'status' => 'error',
                'errors' => ['token' => ['Invalid or missing X-Hovera-Embed-Token header.']],
            ], 403);
        }

        try {
            $data = $this->validate($request);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $from = $this->geocoder->geocode($data['pickup_address']);
            $to = $this->geocoder->geocode($data['dropoff_address']);
        } catch (GeocodingException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => ['address' => [$e->getMessage()]],
            ], 422);
        }

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'direct',
            'targeted_transporter_ids' => [$tenant->id],
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

        $this->dispatcher->dispatch($lead);

        return response()->json([
            'status' => 'ok',
            'inquiry_id' => $lead->id,
        ]);
    }

    private function resolveTenant(string $slug): ?Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        return Tenant::query()
            ->where('slug', $slug)
            ->where('type', TenantType::Transporter)
            ->where('verification_status', VerificationStatus::Verified)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->first();
    }

    /**
     * Constant-time compare embed token z header'a vs tenant.embed_api_token.
     * Brak tokenu w DB albo header'ze → false (deny).
     */
    private function verifyEmbedToken(Request $request, Tenant $tenant): bool
    {
        $provided = (string) $request->headers->get('X-Hovera-Embed-Token', '');
        if ($provided === '') {
            return false;
        }

        $stored = (string) ($tenant->embed_api_token ?? '');
        if ($stored === '') {
            return false;
        }

        return hash_equals($stored, $provided);
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
        ]);
    }
}
