<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Providers;

use App\Domain\Transport\Routing\Contracts\RoutingProvider;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\Route;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Models\Central\SystemSetting;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouteService — darmowy tier 2000 req/dzień. Endpoint:
 *   POST https://api.openrouteservice.org/v2/directions/{profile}/json
 *
 * Profile dla nas:
 *   - driving-hgv  → HGV (ciężarowy z restrykcjami)
 *   - driving-car  → osobowy (fallback gdy profile != 'truck')
 *
 * Klucz w `Authorization` header (string, nie Bearer).
 *
 * Patrz: https://openrouteservice.org/dev/#/api-docs/v2/directions
 */
class OpenRouteServiceProvider implements RoutingProvider
{
    private const BASE_URL = 'https://api.openrouteservice.org/v2/directions';

    private string $apiKey;

    private int $timeoutSeconds;

    public function __construct(
        private readonly HttpFactory $http,
        ?string $apiKey = null,
        ?int $timeoutSeconds = null,
    ) {
        // SystemSetting > .env config — patrz /admin/maps-providers-settings.
        $this->apiKey = $apiKey
            ?? SystemSetting::getSecret('transport.ors.api_key')
            ?? (string) config('transport.providers.ors.api_key', '');
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('transport.providers.ors.timeout', 15);
    }

    public function withKey(string $apiKey): self
    {
        $copy = clone $this;
        $copy->apiKey = $apiKey;

        return $copy;
    }

    public function id(): string
    {
        return 'ors';
    }

    public function calculateRoute(Coords $from, Coords $to, RouteOptions $options): Route
    {
        if ($this->apiKey === '') {
            throw RoutingException::missingCredentials($this->id());
        }

        $body = [
            'coordinates' => [
                [$from->lng, $from->lat],
                [$to->lng, $to->lat],
            ],
            'units' => 'km',
        ];

        $optionsPayload = [];
        if ($options->avoidTolls || $options->avoidFerries) {
            $avoid = [];
            if ($options->avoidTolls) {
                $avoid[] = 'tollways';
            }
            if ($options->avoidFerries) {
                $avoid[] = 'ferries';
            }
            $optionsPayload['avoid_features'] = $avoid;
        }

        $primaryProfile = $options->profile === 'truck' ? 'driving-hgv' : 'driving-car';

        // Restrykcje wagi/wysokości — tylko dla HGV profile. Mapowanie do
        // profile_params.restrictions wg https://openrouteservice.org/dev/#/api-docs/v2/directions
        //   - weight: tony (np. 7.5)
        //   - height: metry (np. 4.0)
        // Pomijamy null'e — ORS używa wtedy default HGV bez restrykcji.
        if ($primaryProfile === 'driving-hgv') {
            $restrictions = [];
            if ($options->weightTons !== null && $options->weightTons > 0) {
                $restrictions['weight'] = $options->weightTons;
            }
            if ($options->heightMeters !== null && $options->heightMeters > 0) {
                $restrictions['height'] = $options->heightMeters;
            }
            if ($restrictions !== []) {
                $optionsPayload['vehicle_type'] = 'hgv';
                $optionsPayload['profile_params'] = ['restrictions' => $restrictions];
            }
        }

        if ($optionsPayload !== []) {
            $body['options'] = $optionsPayload;
        }

        $response = $this->client()->post(self::BASE_URL.'/'.$primaryProfile.'/json', $body);

        // ORS HGV profile aggressively filters drogi z restrykcjami tonażowymi —
        // dla tras PL→PL na DK/DW potrafi zwrócić 404 "Route could not be found"
        // (code 2009) gdy primary profile to HGV. Fallback na driving-car nie jest
        // idealny (samochód osobowy ignoruje truck restrictions), ale zwraca
        // sensowny estimate odległości/czasu zamiast wywalać cały quote flow.
        // Patrz: https://github.com/GIScience/openrouteservice/issues/802
        if ($primaryProfile === 'driving-hgv' && $this->isNoRouteFound($response)) {
            Log::info('ORS driving-hgv returned no route; falling back to driving-car', [
                'from' => [$from->lng, $from->lat],
                'to' => [$to->lng, $to->lat],
                'http_status' => $response->status(),
            ]);
            $response = $this->client()->post(self::BASE_URL.'/driving-car/json', $body);
        }

        if (! $response->successful()) {
            throw RoutingException::apiError($this->id(), $response->status(), (string) $response->body());
        }

        $route = $response->json('routes.0');
        if (! is_array($route)) {
            throw RoutingException::noRoute($this->id());
        }

        $distanceKm = (float) ($route['summary']['distance'] ?? 0);
        $durationSeconds = (int) round((float) ($route['summary']['duration'] ?? 0));

        return new Route(
            distanceKm: $distanceKm,
            durationSeconds: $durationSeconds,
            polyline: $route['geometry'] ?? null,
            providerId: $this->id(),
        );
    }

    /**
     * ORS zwraca 404 + `error.code = 2009` gdy nie znajdzie trasy dla danego
     * profile'u. Inne 404 (np. zły endpoint) mają inne kody — sprawdzamy
     * konkretnie 2009 żeby nie schować bug'ów konfiguracyjnych.
     */
    private function isNoRouteFound(Response $response): bool
    {
        if ($response->status() !== 404) {
            return false;
        }

        return (int) $response->json('error.code') === 2009;
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->withHeaders([
                'Authorization' => $this->apiKey,
                'Accept' => 'application/json, application/geo+json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeoutSeconds);
    }
}
