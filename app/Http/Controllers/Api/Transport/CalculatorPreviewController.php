<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Transport;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Enums\CalculationMode;
use App\Models\Central\Tenant;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Live preview kalkulatora — bije się z JS (Alpine fetch + debounce 500ms)
 * z `/transport/calculator` przy każdej zmianie pola. Zwraca pełną wycenę
 * (Quotation DTO) jako JSON żeby sticky summary card mógł się przeliczać
 * bez submit'u Livewire'a.
 *
 * Endpoint:
 *   POST /api/transport/calculator/preview
 *
 * Auth: session (auth:sanctum SPA mode) + role gate TRANSPORT_OPERATORS.
 * Throttle: 60 req/min/user — preview może być spam'owany przy szybkim
 * typie, ale debounce po stronie JS trzyma to w okolicach 5–10/min/user
 * w realnym użyciu.
 *
 * Body (JSON):
 *   - from_address (string, required gdy brak from_lat/lng)
 *   - to_address (string, required gdy brak to_lat/lng)
 *   - from_lat, from_lng, to_lat, to_lng (float, opcjonalne — gdy
 *     podane, pomijamy geocoding i oszczędzamy Mapbox quota)
 *   - calculation_mode ('one_way' | 'round_trip' | 'return_home')
 *   - loaded (bool, default true)
 *   - horses_count (int 1–30, default 1)
 *   - fixed_fees (array<{name, amount}>, default null = settings default)
 *   - surcharge_percent (float|null)
 *   - waypoints (array<{lat, lng}>, default [])
 *   - avoid_tolls, avoid_ferries (bool, default false)
 *   - profile ('truck'|'car', default 'truck')
 *
 * Response (200):
 *   {
 *     "quotation": { ... Quotation::toArray() ... },
 *     "from": {"address": "...", "lat": ..., "lng": ...},
 *     "to":   {"address": "...", "lat": ..., "lng": ...}
 *   }
 *
 * Response (422):
 *   {"error": "...", "field"?: "from_address"}
 */
class CalculatorPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        TenantManager $tenants,
        TenantRoleGate $roles,
        MapboxGeocoder $geocoder,
        CalculatorService $calculator,
    ): JsonResponse {
        // Tenant musi być załadowany (HydrateTenantConnectionFromSession
        // robi to dla stateful API requestów). Bez tego CalculatorService
        // padnie przy odczycie TransportSettings.
        $tenant = $tenants->current();
        if (! $tenant instanceof Tenant) {
            return response()->json(['error' => __('transport/calculator.error.no_tenant')], 422);
        }

        // Role gate identyczny jak Calculator page — operator/owner/admin/manager.
        if (! $roles->allows(TenantRoleGate::TRANSPORT_OPERATORS)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Defensive parse — JSON body lub form data; każde pole opcjonalne,
        // walidujemy minimum (adresy / coords obecne).
        $payload = $this->normalisePayload($request);

        // Geocodujemy oba adresy oddzielnie żeby precyzyjnie wskazać które
        // pole jest błędne (UI może zaznaczyć je czerwonym borderem).
        try {
            $from = $this->resolveCoords(
                $geocoder,
                $payload['from_address'],
                $payload['from_lat'],
                $payload['from_lng'],
            );
        } catch (GeocodingException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage(), 'field' => 'from_address'], 422);
        }
        try {
            $to = $this->resolveCoords(
                $geocoder,
                $payload['to_address'],
                $payload['to_lat'],
                $payload['to_lng'],
            );
        } catch (GeocodingException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage(), 'field' => 'to_address'], 422);
        }

        $mode = CalculationMode::tryFrom($payload['calculation_mode']) ?? CalculationMode::OneWay;

        try {
            $quotation = $calculator->calculate(
                $tenant,
                new Coords($from['lat'], $from['lng']),
                new Coords($to['lat'], $to['lng']),
                new CalculationOptions(
                    loaded: $payload['loaded'],
                    roundTrip: $mode === CalculationMode::RoundTrip,
                    avoidTolls: $payload['avoid_tolls'],
                    avoidFerries: $payload['avoid_ferries'],
                    routingProfile: $payload['profile'],
                    mode: $mode,
                    horsesCount: $payload['horses_count'],
                    fixedFees: $payload['fixed_fees'],
                    surchargePercent: $payload['surcharge_percent'],
                    waypoints: $payload['waypoints'],
                ),
            );
        } catch (RoutingException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => __('transport/calculator.error.unknown')], 500);
        }

        return response()->json([
            'quotation' => $quotation->toArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Parsuje request body z fallback'ami i klampowaniem zakresów.
     *
     * @return array{
     *   from_address: ?string, to_address: ?string,
     *   from_lat: ?float, from_lng: ?float, to_lat: ?float, to_lng: ?float,
     *   calculation_mode: string, loaded: bool, horses_count: int,
     *   fixed_fees: ?list<array{name: string, amount: float}>,
     *   surcharge_percent: ?float,
     *   waypoints: list<array{lat: float, lng: float}>,
     *   avoid_tolls: bool, avoid_ferries: bool, profile: string,
     * }
     */
    private function normalisePayload(Request $request): array
    {
        return [
            'from_address' => $this->stringOrNull($request->input('from_address')),
            'to_address' => $this->stringOrNull($request->input('to_address')),
            'from_lat' => $this->floatOrNull($request->input('from_lat')),
            'from_lng' => $this->floatOrNull($request->input('from_lng')),
            'to_lat' => $this->floatOrNull($request->input('to_lat')),
            'to_lng' => $this->floatOrNull($request->input('to_lng')),
            'calculation_mode' => (string) $request->input('calculation_mode', 'one_way'),
            'loaded' => $request->boolean('loaded', true),
            'horses_count' => max(1, min(30, (int) $request->input('horses_count', 1))),
            'fixed_fees' => $this->parseFixedFees($request->input('fixed_fees')),
            'surcharge_percent' => $this->parseSurcharge($request->input('surcharge_percent')),
            'waypoints' => $this->parseWaypoints($request->input('waypoints')),
            'avoid_tolls' => $request->boolean('avoid_tolls'),
            'avoid_ferries' => $request->boolean('avoid_ferries'),
            'profile' => in_array($request->input('profile'), ['truck', 'car'], true)
                ? (string) $request->input('profile')
                : 'truck',
        ];
    }

    /**
     * Geocoduje adres jeśli nie podano coords, inaczej reusuje cached. Rzuca
     * InvalidArgumentException gdy nic nie wystarczy (UI powinien wtedy
     * pominąć preview a nie pokazywać error).
     *
     * @return array{address: string, lat: float, lng: float}
     */
    private function resolveCoords(
        MapboxGeocoder $geocoder,
        ?string $address,
        ?float $lat,
        ?float $lng,
    ): array {
        // Coords podane przez frontend (np. z autocomplete pickera) — najtańsza
        // ścieżka, bez Mapbox call'a.
        if ($lat !== null && $lng !== null) {
            return [
                'address' => $address ?? '',
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        if ($address === null || trim($address) === '') {
            throw new \InvalidArgumentException(__('transport/calculator.error.unknown'));
        }

        $result = $geocoder->geocode($address);

        return [
            'address' => $result->displayName,
            'lat' => $result->coords->lat,
            'lng' => $result->coords->lng,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Defensive — gdy JS nie wysłał, settings default zostanie użyty.
     * Pusta tablica = user opt-out (po stronie CalculatorService = brak doliczeń).
     *
     * @return ?list<array{name: string, amount: float}>
     */
    private function parseFixedFees(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $amount = is_numeric($item['amount'] ?? null) ? (float) $item['amount'] : 0.0;
            if ($name === '' || $amount <= 0) {
                continue;
            }
            $out[] = ['name' => $name, 'amount' => $amount];
        }

        return $out;
    }

    private function parseSurcharge(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return max(0.0, (float) $raw);
    }

    /**
     * Filtruje waypointy z poprawnymi lat/lng (defensive — frontend
     * może wysłać pusty Repeater item).
     *
     * @return list<array{lat: float, lng: float}>
     */
    private function parseWaypoints(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $wp) {
            if (! is_array($wp)) {
                continue;
            }
            $lat = is_numeric($wp['lat'] ?? null) ? (float) $wp['lat'] : null;
            $lng = is_numeric($wp['lng'] ?? null) ? (float) $wp['lng'] : null;
            if ($lat === null || $lng === null) {
                continue;
            }
            $out[] = ['lat' => $lat, 'lng' => $lng];
        }

        return $out;
    }
}
