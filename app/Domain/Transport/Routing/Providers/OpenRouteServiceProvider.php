<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Providers;

use App\Domain\Transport\Routing\Contracts\RoutingProvider;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\Route;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;

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
        $this->apiKey = $apiKey ?? (string) config('transport.providers.ors.api_key', '');
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

        $profile = $options->profile === 'truck' ? 'driving-hgv' : 'driving-car';

        $body = [
            'coordinates' => [
                [$from->lng, $from->lat],
                [$to->lng, $to->lat],
            ],
            'units' => 'km',
        ];

        if ($options->avoidTolls || $options->avoidFerries) {
            $avoid = [];
            if ($options->avoidTolls) {
                $avoid[] = 'tollways';
            }
            if ($options->avoidFerries) {
                $avoid[] = 'ferries';
            }
            $body['options'] = ['avoid_features' => $avoid];
        }

        $response = $this->client()->post(self::BASE_URL.'/'.$profile.'/json', $body);

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
