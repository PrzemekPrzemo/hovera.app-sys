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

/**
 * Mapbox Directions API. Endpoint:
 *   GET https://api.mapbox.com/directions/v5/{routing_profile}/{coords}
 *
 * UWAGA: Mapbox NIE MA natywnego profile HGV. Używamy `driving` (lub
 * `driving-traffic` z live traffic). Dla precyzji ciężarowej polecamy
 * ORS (`driving-hgv`) lub Google (`TRUCK`). Mapbox zostaje świetny do
 * brandingu mapy (Mapbox Studio) i szybkości UI.
 *
 * Patrz: https://docs.mapbox.com/api/navigation/directions/
 */
class MapboxProvider implements RoutingProvider
{
    private const BASE_URL = 'https://api.mapbox.com/directions/v5/mapbox';

    private string $accessToken;

    private int $timeoutSeconds;

    public function __construct(
        private readonly HttpFactory $http,
        ?string $accessToken = null,
        ?int $timeoutSeconds = null,
    ) {
        // SystemSetting > .env config — patrz MapboxGeocoder + /admin/maps-providers-settings.
        $this->accessToken = $accessToken
            ?? SystemSetting::getSecret('transport.mapbox.token')
            ?? (string) config('transport.providers.mapbox.access_token', '');
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('transport.providers.mapbox.timeout', 15);
    }

    public function withKey(string $accessToken): self
    {
        $copy = clone $this;
        $copy->accessToken = $accessToken;

        return $copy;
    }

    public function id(): string
    {
        return 'mapbox';
    }

    public function calculateRoute(Coords $from, Coords $to, RouteOptions $options): Route
    {
        if ($this->accessToken === '') {
            throw RoutingException::missingCredentials($this->id());
        }

        // Mapbox profile mapping:
        //   - 'truck' & 'fast'  → driving-traffic (live, lepsza dokładność)
        //   - 'car'            → driving
        $routingProfile = match ($options->profile) {
            'car' => 'driving',
            default => 'driving-traffic',
        };

        $coords = sprintf('%f,%f;%f,%f', $from->lng, $from->lat, $to->lng, $to->lat);

        $query = [
            'access_token' => $this->accessToken,
            'geometries' => 'polyline',           // Google-compatible encoded polyline
            'overview' => 'simplified',
        ];

        $exclude = [];
        if ($options->avoidTolls) {
            $exclude[] = 'toll';
        }
        if ($options->avoidFerries) {
            $exclude[] = 'ferry';
        }
        if (! empty($exclude)) {
            $query['exclude'] = implode(',', $exclude);
        }

        $response = $this->http
            ->timeout($this->timeoutSeconds)
            ->get(self::BASE_URL.'/'.$routingProfile.'/'.$coords, $query);

        if (! $response->successful()) {
            throw RoutingException::apiError($this->id(), $response->status(), (string) $response->body());
        }

        $route = $response->json('routes.0');
        if (! is_array($route)) {
            throw RoutingException::noRoute($this->id());
        }

        return new Route(
            distanceKm: round(((float) $route['distance']) / 1000, 2),
            durationSeconds: (int) round((float) $route['duration']),
            polyline: is_string($route['geometry'] ?? null) ? $route['geometry'] : null,
            providerId: $this->id(),
        );
    }
}
