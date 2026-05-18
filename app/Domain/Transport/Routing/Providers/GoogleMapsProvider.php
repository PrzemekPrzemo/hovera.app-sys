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
 * Google Routes API (v2). Endpoint:
 *   POST https://routes.googleapis.com/directions/v2:computeRoutes
 *
 * Wymaga API key i FieldMask header żeby zwrócić tylko potrzebne pola
 * (Google bije ceną per field). Travel mode `TRUCK` daje HGV routing
 * (mosty, restrykcje wagi, ograniczenia ciężarówek).
 *
 * Patrz: https://developers.google.com/maps/documentation/routes/compute_route_directions
 */
class GoogleMapsProvider implements RoutingProvider
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private string $apiKey;

    private int $timeoutSeconds;

    public function __construct(
        private readonly HttpFactory $http,
        ?string $apiKey = null,
        ?int $timeoutSeconds = null,
    ) {
        $this->apiKey = $apiKey ?? (string) config('transport.providers.google.api_key', '');
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('transport.providers.google.timeout', 15);
    }

    public function withKey(string $apiKey): self
    {
        $copy = clone $this;
        $copy->apiKey = $apiKey;

        return $copy;
    }

    public function id(): string
    {
        return 'google';
    }

    public function calculateRoute(Coords $from, Coords $to, RouteOptions $options): Route
    {
        if ($this->apiKey === '') {
            throw RoutingException::missingCredentials($this->id());
        }

        $travelMode = $options->profile === 'truck' ? 'TRUCK' : 'DRIVE';

        $body = [
            'origin' => ['location' => ['latLng' => ['latitude' => $from->lat, 'longitude' => $from->lng]]],
            'destination' => ['location' => ['latLng' => ['latitude' => $to->lat, 'longitude' => $to->lng]]],
            'travelMode' => $travelMode,
            'polylineQuality' => 'OVERVIEW',
            'units' => 'METRIC',
        ];

        if ($travelMode === 'DRIVE') {
            // routingPreference jest dozwolone tylko dla DRIVE/TWO_WHEELER w Routes v2.
            $body['routingPreference'] = 'TRAFFIC_AWARE';
        }

        if ($options->avoidTolls || $options->avoidFerries) {
            $body['routeModifiers'] = array_filter([
                'avoidTolls' => $options->avoidTolls ?: null,
                'avoidFerries' => $options->avoidFerries ?: null,
            ]);
        }

        $response = $this->client()->post(self::ENDPOINT, $body);

        if (! $response->successful()) {
            throw RoutingException::apiError($this->id(), $response->status(), (string) $response->body());
        }

        $route = $response->json('routes.0');
        if (! is_array($route)) {
            throw RoutingException::noRoute($this->id());
        }

        $distanceMeters = (int) ($route['distanceMeters'] ?? 0);
        // Google zwraca duration jako string ISO 8601 (np. "1234s"). Trzymamy parser konserwatywny.
        $rawDuration = (string) ($route['duration'] ?? '0s');
        $durationSeconds = (int) preg_replace('/[^0-9]/', '', $rawDuration);

        return new Route(
            distanceKm: round($distanceMeters / 1000, 2),
            durationSeconds: $durationSeconds,
            polyline: is_string($route['polyline']['encodedPolyline'] ?? null)
                ? $route['polyline']['encodedPolyline']
                : null,
            providerId: $this->id(),
        );
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                // FieldMask MUSI być ustawione — bez tego Routes API zwraca 400.
                'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeoutSeconds);
    }
}
