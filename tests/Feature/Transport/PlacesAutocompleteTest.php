<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Geocoding\Autocomplete\PlacesAutocompleteService;
use App\Domain\Transport\Geocoding\Autocomplete\Providers\MapboxAutocompleteProvider;
use App\Domain\Transport\Geocoding\Autocomplete\Providers\PhotonProvider;
use App\Models\Central\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlacesAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_photon_provider_parses_features_into_suggestions(): void
    {
        Http::fake([
            'photon.komoot.io/*' => Http::response([
                'features' => [
                    [
                        'geometry' => ['coordinates' => [21.0163, 52.2307]],
                        'properties' => [
                            'name' => 'Marszałkowska',
                            'street' => 'Marszałkowska',
                            'housenumber' => '1',
                            'postcode' => '00-001',
                            'city' => 'Warszawa',
                            'country' => 'Polska',
                        ],
                    ],
                ],
            ]),
        ]);

        $provider = new PhotonProvider(app(HttpFactory::class));
        $items = $provider->suggest('marszalkowska');

        $this->assertCount(1, $items);
        $this->assertSame('photon', $items[0]->providerId);
        $this->assertSame(52.2307, $items[0]->lat);
        $this->assertStringContainsString('Marszałkowska', $items[0]->label);
        $this->assertStringContainsString('00-001 Warszawa', $items[0]->label);
        $this->assertStringContainsString('Polska', $items[0]->label);
    }

    public function test_photon_returns_empty_array_for_blank_query(): void
    {
        $provider = new PhotonProvider(app(HttpFactory::class));

        $this->assertSame([], $provider->suggest('   '));
    }

    public function test_mapbox_provider_returns_empty_when_no_token(): void
    {
        $provider = new MapboxAutocompleteProvider(app(HttpFactory::class), accessToken: '');
        $this->assertFalse($provider->isAvailable());
        $this->assertSame([], $provider->suggest('warszawa'));
    }

    public function test_mapbox_provider_parses_features(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/v5/mapbox.places/*' => Http::response([
                'features' => [[
                    'place_name' => 'Warszawa, Mazowieckie, Polska',
                    'center' => [21.0122, 52.2297],
                ]],
            ]),
        ]);

        $provider = new MapboxAutocompleteProvider(app(HttpFactory::class), accessToken: 'pk.test');
        $items = $provider->suggest('warszawa');

        $this->assertCount(1, $items);
        $this->assertSame('mapbox', $items[0]->providerId);
        $this->assertSame('Warszawa, Mazowieckie, Polska', $items[0]->label);
    }

    public function test_service_picks_photon_for_public_by_default(): void
    {
        $service = app(PlacesAutocompleteService::class);

        $this->assertSame('photon', $service->providerNameFor('public'));
    }

    public function test_service_picks_mapbox_for_panel_by_default(): void
    {
        $service = app(PlacesAutocompleteService::class);

        $this->assertSame('mapbox', $service->providerNameFor('panel'));
    }

    public function test_service_falls_back_from_mapbox_to_photon_when_token_missing(): void
    {
        // Default panel = mapbox, ale token nie ustawiony → soft fallback na Photon.
        Http::fake([
            'photon.komoot.io/*' => Http::response(['features' => [[
                'geometry' => ['coordinates' => [21.0, 52.0]],
                'properties' => ['name' => 'Test', 'city' => 'Test'],
            ]]]),
        ]);

        $service = app(PlacesAutocompleteService::class);
        $provider = $service->resolveProvider('panel');

        $this->assertSame('photon', $provider?->id());
    }

    public function test_service_returns_null_when_off(): void
    {
        SystemSetting::setValue('transport.autocomplete.provider_panel', 'off');
        $service = app(PlacesAutocompleteService::class);

        $this->assertFalse($service->isEnabledFor('panel'));
        $this->assertNull($service->resolveProvider('panel'));
        $this->assertSame([], $service->suggest('panel', 'warszawa'));
    }

    public function test_endpoint_returns_suggestions_with_provider_label(): void
    {
        Http::fake([
            'photon.komoot.io/*' => Http::response(['features' => [[
                'geometry' => ['coordinates' => [21.0163, 52.2307]],
                'properties' => ['name' => 'Marszałkowska', 'city' => 'Warszawa', 'country' => 'Polska'],
            ]]]),
        ]);

        $response = $this->getJson('/api/transport/places/suggest?q=marszalkowska&context=public');

        $response->assertOk();
        $response->assertJson([
            'provider' => 'photon',
            'items' => [[
                'label' => 'Marszałkowska, Warszawa, Polska',
                'provider' => 'photon',
            ]],
        ]);
    }

    public function test_endpoint_short_queries_return_empty(): void
    {
        $response = $this->getJson('/api/transport/places/suggest?q=ab&context=public');

        $response->assertOk();
        $response->assertJson(['items' => []]);
    }

    public function test_endpoint_returns_empty_when_provider_is_off(): void
    {
        SystemSetting::setValue('transport.autocomplete.provider_public', 'off');

        $response = $this->getJson('/api/transport/places/suggest?q=warszawa&context=public');

        $response->assertOk();
        $response->assertJson(['provider' => 'off', 'items' => []]);
    }
}
