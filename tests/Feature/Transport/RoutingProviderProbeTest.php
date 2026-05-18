<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Routing\RoutingProviderProbe;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingProviderProbeTest extends TestCase
{
    public function test_empty_key_returns_failure(): void
    {
        $result = app(RoutingProviderProbe::class)->test('mapbox', '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('klucz', $result['message']);   // PL default in tests
    }

    public function test_valid_mapbox_key_returns_success_with_distance(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [['distance' => 100.0, 'duration' => 30]],
            ]),
        ]);

        $result = app(RoutingProviderProbe::class)->test('mapbox', 'pk.test-token');

        $this->assertTrue($result['success']);
        $this->assertSame(0.1, $result['distance_km']);
        $this->assertStringContainsString('mapbox', $result['message']);
        $this->assertStringContainsString('0.100 km', $result['message']);
    }

    public function test_invalid_mapbox_key_returns_failure_from_api(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['message' => 'Not Authorized'], 401),
        ]);

        $result = app(RoutingProviderProbe::class)->test('mapbox', 'pk.invalid');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('HTTP 401', $result['message']);
    }

    public function test_unsupported_provider_returns_failure(): void
    {
        $result = app(RoutingProviderProbe::class)->test('garbage-provider', 'any-key');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unsupported', $result['message']);
    }

    public function test_ors_probe_passes_truck_call_through(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [['summary' => ['distance' => 0.1, 'duration' => 30]]],
            ]),
        ]);

        $result = app(RoutingProviderProbe::class)->test('ors', 'ors-test-key');

        $this->assertTrue($result['success']);
        $this->assertSame(0.1, $result['distance_km']);
    }

    public function test_google_probe_handles_routes_response(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 100,
                    'duration' => '30s',
                    'polyline' => ['encodedPolyline' => 'abc'],
                ]],
            ]),
        ]);

        $result = app(RoutingProviderProbe::class)->test('google', 'AIza-test');

        $this->assertTrue($result['success']);
        $this->assertSame(0.1, $result['distance_km']);
    }
}
