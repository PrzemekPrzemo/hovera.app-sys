<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Domain\Transport\Routing\Providers\GoogleMapsProvider;
use App\Domain\Transport\Routing\Providers\MapboxProvider;
use App\Domain\Transport\Routing\Providers\OpenRouteServiceProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingProvidersTest extends TestCase
{
    public function test_ors_parses_response_and_uses_hgv_profile_for_truck(): void
    {
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-hgv/json' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 295.50, 'duration' => 13_500],
                    'geometry' => '_p~iF~ps|U_ulLnnqC',
                ]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'test-key');
        $route = $provider->calculateRoute(
            new Coords(52.2297, 21.0122),
            new Coords(50.0647, 19.9450),
            new RouteOptions(profile: 'truck'),
        );

        $this->assertSame('ors', $route->providerId);
        $this->assertSame(295.50, $route->distanceKm);
        $this->assertSame(13_500, $route->durationSeconds);
        $this->assertSame('_p~iF~ps|U_ulLnnqC', $route->polyline);
    }

    public function test_ors_throws_without_credentials(): void
    {
        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: '');

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('requires an API key');

        $provider->calculateRoute(
            new Coords(0, 0),
            new Coords(1, 1),
            new RouteOptions(),
        );
    }

    public function test_ors_throws_on_api_error(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['error' => 'quota exceeded'], 429),
        ]);
        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'k');

        $this->expectException(RoutingException::class);
        $provider->calculateRoute(new Coords(0, 0), new Coords(1, 1), new RouteOptions());
    }

    public function test_mapbox_uses_driving_traffic_for_truck_profile(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [[
                    'distance' => 295_500.0,    // meters
                    'duration' => 12_600.0,
                    'geometry' => 'abc_def',
                ]],
            ]),
        ]);

        $provider = new MapboxProvider(app(HttpFactory::class), accessToken: 'pk.test');
        $route = $provider->calculateRoute(
            new Coords(52.2297, 21.0122),
            new Coords(50.0647, 19.9450),
            new RouteOptions(profile: 'truck'),
        );

        $this->assertSame('mapbox', $route->providerId);
        $this->assertSame(295.50, $route->distanceKm);
        $this->assertSame(12_600, $route->durationSeconds);
        $this->assertSame('abc_def', $route->polyline);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/driving-traffic/')
                && str_contains($request->url(), 'access_token=pk.test');
        });
    }

    public function test_mapbox_uses_driving_for_car_profile(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [['distance' => 1000, 'duration' => 60]],
            ]),
        ]);

        $provider = new MapboxProvider(app(HttpFactory::class), accessToken: 'pk.test');
        $provider->calculateRoute(new Coords(0, 0), new Coords(1, 1), new RouteOptions(profile: 'car'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/driving/') && ! str_contains($r->url(), '/driving-traffic/'));
    }

    public function test_google_uses_truck_travel_mode(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 295_500,
                    'duration' => '12600s',
                    'polyline' => ['encodedPolyline' => 'xyz123'],
                ]],
            ]),
        ]);

        $provider = new GoogleMapsProvider(app(HttpFactory::class), apiKey: 'AIza-test');
        $route = $provider->calculateRoute(
            new Coords(52.2297, 21.0122),
            new Coords(50.0647, 19.9450),
            new RouteOptions(profile: 'truck'),
        );

        $this->assertSame('google', $route->providerId);
        $this->assertSame(295.50, $route->distanceKm);
        $this->assertSame(12_600, $route->durationSeconds);
        $this->assertSame('xyz123', $route->polyline);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['travelMode'] === 'TRUCK'
                && $request->header('X-Goog-Api-Key')[0] === 'AIza-test'
                && str_contains($request->header('X-Goog-FieldMask')[0], 'distanceMeters');
        });
    }

    public function test_google_throws_without_key(): void
    {
        $provider = new GoogleMapsProvider(app(HttpFactory::class), apiKey: '');

        $this->expectException(RoutingException::class);
        $provider->calculateRoute(new Coords(0, 0), new Coords(1, 1), new RouteOptions());
    }

    public function test_provider_ids_match_documented_values(): void
    {
        $http = app(HttpFactory::class);

        $this->assertSame('ors', (new OpenRouteServiceProvider($http))->id());
        $this->assertSame('mapbox', (new MapboxProvider($http))->id());
        $this->assertSame('google', (new GoogleMapsProvider($http))->id());
    }
}
