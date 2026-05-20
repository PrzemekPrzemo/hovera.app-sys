<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransportSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_trsett_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTransportSettingsTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_current_creates_default_row_on_first_call(): void
    {
        $this->assertSame(0, TransportSettings::count());

        $settings = TransportSettings::current();

        $this->assertSame(1, TransportSettings::count());
        $this->assertEqualsWithDelta(4.50, (float) $settings->rate_per_km, 0.001);
        $this->assertEqualsWithDelta(800.00, (float) $settings->minimum_charge, 0.001);
        $this->assertTrue($settings->fuel_surcharge_enabled);
        $this->assertSame('PLN', $settings->currency);
        $this->assertSame(['provider' => 'ors'], $settings->routing_provider);
    }

    public function test_current_is_idempotent_singleton(): void
    {
        $first = TransportSettings::current();
        $second = TransportSettings::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TransportSettings::count());
    }

    public function test_routing_provider_round_trips_as_array(): void
    {
        $settings = TransportSettings::current();
        $settings->forceFill([
            'routing_provider' => ['provider' => 'google', 'api_key' => 'AIza...'],
        ])->save();

        $fresh = TransportSettings::current();
        $this->assertSame('google', $fresh->routing_provider['provider']);
        $this->assertSame('AIza...', $fresh->routing_provider['api_key']);
    }

    public function test_filament_route_is_registered(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($routes->contains('filament.transport.pages.transport-settings'));

    }

    public function test_defaults_match_documented_starting_point(): void
    {
        $defaults = TransportSettings::defaults();

        $this->assertSame('PLN', $defaults['currency']);
        $this->assertSame(['provider' => 'ors'], $defaults['routing_provider']);
        $this->assertEqualsWithDelta(4.50, $defaults['rate_per_km'], 0.001);
        $this->assertTrue($defaults['fuel_surcharge_enabled']);
    }

    private function setUpTransportSettingsTable(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('extra_horse_fee_default', 8, 2)->default(0);
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
