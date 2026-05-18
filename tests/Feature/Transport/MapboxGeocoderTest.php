<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapboxGeocoderTest extends TestCase
{
    public function test_geocode_returns_coords_for_known_address(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'features' => [[
                    'place_name' => 'ul. Marymoncka 1, Warszawa, Polska',
                    'center' => [20.9921, 52.2818],  // [lng, lat] per Mapbox
                ]],
            ]),
        ]);

        $geocoder = new MapboxGeocoder(app(HttpFactory::class), accessToken: 'pk.test');
        $result = $geocoder->geocode('Marymoncka 1, Warszawa');

        $this->assertSame('ul. Marymoncka 1, Warszawa, Polska', $result->displayName);
        $this->assertEqualsWithDelta(52.2818, $result->coords->lat, 0.0001);
        $this->assertEqualsWithDelta(20.9921, $result->coords->lng, 0.0001);
        $this->assertSame('pl', $result->countryCode);
    }

    public function test_geocode_throws_without_credentials(): void
    {
        $geocoder = new MapboxGeocoder(app(HttpFactory::class), accessToken: '');

        $this->expectException(GeocodingException::class);
        $this->expectExceptionMessage('requires an API key');

        $geocoder->geocode('anywhere');
    }

    public function test_geocode_throws_when_no_features_returned(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => []]),
        ]);

        $geocoder = new MapboxGeocoder(app(HttpFactory::class), accessToken: 'pk.test');

        $this->expectException(GeocodingException::class);
        $this->expectExceptionMessage('Nie znaleziono');

        $geocoder->geocode('zupelnie nieistniejaca lokacja xyz');
    }

    public function test_geocode_throws_on_http_error(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response('', 401),
        ]);

        $geocoder = new MapboxGeocoder(app(HttpFactory::class), accessToken: 'pk.test');

        $this->expectException(GeocodingException::class);
        $this->expectExceptionMessage('HTTP 401');

        $geocoder->geocode('Warszawa');
    }

    public function test_geocode_throws_on_empty_query(): void
    {
        $geocoder = new MapboxGeocoder(app(HttpFactory::class), accessToken: 'pk.test');

        $this->expectException(GeocodingException::class);
        $geocoder->geocode('   ');
    }

    public function test_calculator_route_is_registered_in_transport_panel(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($routes->contains('filament.transport.pages.calculator'));
    }
}
