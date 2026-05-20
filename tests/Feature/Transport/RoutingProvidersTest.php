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

    public function test_ors_sends_vehicle_restrictions_for_hgv_when_weight_and_height_set(): void
    {
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-hgv/json' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 200.0, 'duration' => 9000],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'test-key');
        $provider->calculateRoute(
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new RouteOptions(
                profile: 'truck',
                weightTons: 7.5,
                heightMeters: 3.8,
            ),
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'driving-hgv')) {
                return false;
            }
            $body = json_decode($request->body(), true);

            return ($body['options']['vehicle_type'] ?? null) === 'hgv'
                && ($body['options']['profile_params']['restrictions']['weight'] ?? null) === 7.5
                && ($body['options']['profile_params']['restrictions']['height'] ?? null) === 3.8;
        });
    }

    public function test_ors_omits_restrictions_when_weight_height_null(): void
    {
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-hgv/json' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 200.0, 'duration' => 9000],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'test-key');
        $provider->calculateRoute(
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new RouteOptions(profile: 'truck'),
        );

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            // Brak weight/height → brak profile_params + vehicle_type w body.
            return ! isset($body['options']['profile_params'])
                && ! isset($body['options']['vehicle_type']);
        });
    }

    public function test_ors_does_not_send_restrictions_for_car_profile(): void
    {
        // Restrykcje HGV nie mają sensu dla driving-car (osobowy).
        // Profile params zachowujemy puste nawet gdy user przekaże weight.
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-car/json' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 200.0, 'duration' => 9000],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'test-key');
        $provider->calculateRoute(
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new RouteOptions(
                profile: 'car',
                weightTons: 7.5,
                heightMeters: 3.8,
            ),
        );

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'driving-car')
                && ! isset($body['options']['profile_params']);
        });
    }

    public function test_ors_sends_only_weight_when_height_null(): void
    {
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-hgv/json' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 200.0, 'duration' => 9000],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'test-key');
        $provider->calculateRoute(
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new RouteOptions(profile: 'truck', weightTons: 12.5),
        );

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return ($body['options']['profile_params']['restrictions']['weight'] ?? null) === 12.5
                && ! isset($body['options']['profile_params']['restrictions']['height']);
        });
    }

    public function test_ors_throws_without_credentials(): void
    {
        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: '');

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('requires an API key');

        $provider->calculateRoute(
            new Coords(0, 0),
            new Coords(1, 1),
            new RouteOptions,
        );
    }

    public function test_ors_throws_on_api_error(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(['error' => 'quota exceeded'], 429),
        ]);
        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'k');

        $this->expectException(RoutingException::class);
        $provider->calculateRoute(new Coords(0, 0), new Coords(1, 1), new RouteOptions);
    }

    public function test_ors_falls_back_to_driving_car_when_hgv_returns_2009(): void
    {
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-hgv/json' => Http::response([
                'error' => [
                    'code' => 2009,
                    'message' => 'Route could not be found between points',
                ],
            ], 404),
            'api.openrouteservice.org/v2/directions/driving-car/json' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 350.10, 'duration' => 15_000],
                    'geometry' => 'fallback_poly',
                ]],
            ]),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'k');
        $route = $provider->calculateRoute(
            new Coords(52.2307, 21.0163),  // Warsaw
            new Coords(54.4451, 18.5691),  // Gdynia area
            new RouteOptions(profile: 'truck'),
        );

        $this->assertSame(350.10, $route->distanceKm);
        $this->assertSame(15_000, $route->durationSeconds);
        $this->assertSame('fallback_poly', $route->polyline);
    }

    public function test_ors_does_not_fallback_on_404_with_non_2009_code(): void
    {
        // 404 z innym kodem (np. zły endpoint, błąd konfiguracji) — nie chowamy.
        Http::fake([
            'api.openrouteservice.org/v2/directions/driving-hgv/json' => Http::response([
                'error' => ['code' => 9999, 'message' => 'unknown error'],
            ], 404),
        ]);

        $provider = new OpenRouteServiceProvider(app(HttpFactory::class), apiKey: 'k');

        $this->expectException(RoutingException::class);
        $provider->calculateRoute(
            new Coords(0, 0),
            new Coords(1, 1),
            new RouteOptions(profile: 'truck'),
        );
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
        $provider->calculateRoute(new Coords(0, 0), new Coords(1, 1), new RouteOptions);
    }

    public function test_provider_ids_match_documented_values(): void
    {
        $http = app(HttpFactory::class);

        $this->assertSame('ors', (new OpenRouteServiceProvider($http))->id());
        $this->assertSame('mapbox', (new MapboxProvider($http))->id());
        $this->assertSame('google', (new GoogleMapsProvider($http))->id());
    }
}
