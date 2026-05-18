<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing;

use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Domain\Transport\Routing\Providers\GoogleMapsProvider;
use App\Domain\Transport\Routing\Providers\MapboxProvider;
use App\Domain\Transport\Routing\Providers\OpenRouteServiceProvider;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Test ważności klucza API providera routingu. Wykonuje minimalny call
 * (krótka trasa 100m) — jeśli przejdzie, klucz jest OK. Patrz docs/TRANSPORT.md
 * (krok D z feedbacku produkcyjnego).
 *
 * Koszt: Google ~0.005$ za probe, Mapbox/ORS w darmowym tier'ze.
 */
class RoutingProviderProbe
{
    /** Współrzędne testowe: Warszawa Centrum → 100m na wschód (cheap probe). */
    private const TEST_FROM = ['lat' => 52.2297, 'lng' => 21.0122];

    private const TEST_TO = ['lat' => 52.2297, 'lng' => 21.0137];

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return array{success: bool, message: string, distance_km?: float}
     */
    public function test(string $providerId, string $apiKey): array
    {
        if (trim($apiKey) === '') {
            return ['success' => false, 'message' => __('transport/api_config.probe.empty_key')];
        }

        try {
            $provider = $this->makeProvider($providerId, $apiKey);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        try {
            $route = $provider->calculateRoute(
                new Coords(self::TEST_FROM['lat'], self::TEST_FROM['lng']),
                new Coords(self::TEST_TO['lat'], self::TEST_TO['lng']),
                new RouteOptions(profile: 'car'),
            );
        } catch (RoutingException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => __('transport/api_config.probe.unexpected_error').': '.$e->getMessage()];
        }

        return [
            'success' => true,
            'message' => __('transport/api_config.probe.ok', [
                'provider' => $providerId,
                'km' => number_format($route->distanceKm, 3, '.', ''),
            ]),
            'distance_km' => $route->distanceKm,
        ];
    }

    private function makeProvider(string $providerId, string $apiKey): object
    {
        return match ($providerId) {
            'ors' => new OpenRouteServiceProvider($this->http, apiKey: $apiKey),
            'mapbox' => new MapboxProvider($this->http, accessToken: $apiKey),
            'google' => new GoogleMapsProvider($this->http, apiKey: $apiKey),
            default => throw new \DomainException("Unsupported routing provider: {$providerId}"),
        };
    }
}
