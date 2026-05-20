<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Fuel\FuelPriceService;
use App\Models\Central\FuelPrice;
use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FuelPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_fuel_').'.sqlite';
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

    public function test_falls_back_to_config_default_when_no_data(): void
    {
        config()->set('transport.fuel.fallback_price', 6.99);

        $service = new FuelPriceService;
        $this->assertSame(6.99, $service->current());
    }

    public function test_returns_latest_central_snapshot_within_ttl(): void
    {
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 6.45,
            'snapshot_date' => now()->subDay()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now()->subDay(),
        ]);

        $this->assertSame(6.45, (new FuelPriceService)->current());
    }

    public function test_ignores_stale_snapshot_older_than_ttl(): void
    {
        config()->set('transport.fuel.snapshot_max_age_days', 7);
        config()->set('transport.fuel.fallback_price', 7.10);

        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 6.45,
            'snapshot_date' => now()->subDays(10)->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now()->subDays(10),
        ]);

        $this->assertSame(7.10, (new FuelPriceService)->current());
    }

    public function test_per_tenant_manual_override_wins_over_central(): void
    {
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 6.45,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        TransportSettings::current()->forceFill(['manual_fuel_price_pln' => 5.20])->save();

        $this->assertSame(5.20, (new FuelPriceService)->current());
    }

    public function test_calculate_surcharge_positive_diff(): void
    {
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 7.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        // current=7.50, base=7.00, diff=0.50, consumption 32.5 L/100km, distance 100 km
        //   → litres = 32.5, surcharge = 32.5 * 0.50 = 16.25
        $service = new FuelPriceService;
        $this->assertSame(16.25, $service->calculateSurcharge(
            consumptionLPer100km: 32.5,
            distanceKm: 100,
            basePricePln: 7.00,
        ));
    }

    public function test_calculate_surcharge_zero_when_current_below_base(): void
    {
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 6.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        $this->assertSame(0.0, (new FuelPriceService)->calculateSurcharge(
            consumptionLPer100km: 32.5,
            distanceKm: 500,
            basePricePln: 7.00,
        ));
    }

    public function test_calculate_full_cost_uses_current_price_without_base(): void
    {
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 7.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        // 32.5 L/100 × 100 km = 32.5 L; 32.5 × 7.50 = 243.75
        $this->assertSame(243.75, (new FuelPriceService)->calculateFullCost(
            consumptionLPer100km: 32.5,
            distanceKm: 100,
        ));
    }

    public function test_calculate_full_cost_zero_for_zero_distance(): void
    {
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 7.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        $this->assertSame(0.0, (new FuelPriceService)->calculateFullCost(
            consumptionLPer100km: 32.5,
            distanceKm: 0,
        ));
    }

    private function setUpTransportSettingsTable(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('extra_horse_fee_default', 8, 2)->default(0);
            $t->json('fixed_fees_default')->nullable();
            $t->decimal('surcharge_percent_default', 5, 2)->nullable();
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->string('fuel_calculation_mode', 16)->default('surcharge');
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
