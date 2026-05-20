<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\TenantType;
use App\Models\Central\FuelPrice;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_calc_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTransportSettingsTable();
        $this->tenant = $this->makeTransporterTenant();

        // Domyślny snapshot ceny ON na dziś
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 7.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        // Mock ORS — 100 km, 1h
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 100.0, 'duration' => 3600],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_basic_calculation_with_fuel_surcharge_and_vat(): void
    {
        // defaults: rate 4.50, min 800, consumption 32.5, base price 7.00,
        // surcharge enabled, VAT 23, PLN.
        // distance=100, loaded=true (no loaded rate set → use 4.50)
        //
        // base    = 4.50 * 100 = 450
        // surchg  = (7.50 - 7.00) * 0.325 * 100 = 16.25
        // sub     = 466.25
        // min_adj = 800 - 466.25 = 333.75
        // net     = 800.00
        // vat     = 800 * 0.23 = 184.00
        // gross   = 984.00

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        $this->assertSame(100.0, $q->distanceKm);
        $this->assertSame(3600, $q->durationSeconds);
        $this->assertSame(4.50, $q->rateUsed);
        $this->assertSame(450.0, $q->baseCost);
        $this->assertSame(16.25, $q->fuelSurcharge);
        $this->assertSame(333.75, $q->minimumAdjustment);
        $this->assertSame(800.00, $q->netTotal);
        $this->assertSame(23.00, $q->vatRate);
        $this->assertSame(184.00, $q->vatAmount);
        $this->assertSame(984.00, $q->grossTotal);
        $this->assertSame('PLN', $q->currency);
        $this->assertSame('ors', $q->routingProvider);
        $this->assertSame('POLY', $q->polyline);
    }

    public function test_uses_loaded_rate_when_available_and_loaded_true(): void
    {
        TransportSettings::current()->forceFill([
            'rate_per_km' => 4.50,
            'rate_per_km_loaded' => 6.00,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(loaded: true),
        );

        $this->assertSame(6.00, $q->rateUsed);
        $this->assertSame(600.0, $q->baseCost);
    }

    public function test_uses_base_rate_when_loaded_false(): void
    {
        TransportSettings::current()->forceFill([
            'rate_per_km' => 4.50,
            'rate_per_km_loaded' => 6.00,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(loaded: false),
        );

        $this->assertSame(4.50, $q->rateUsed);
    }

    public function test_round_trip_doubles_distance(): void
    {
        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(roundTrip: true),
        );

        // 100 km × 2 = 200 km
        $this->assertSame(200.0, $q->distanceKm);
        $this->assertSame(900.0, $q->baseCost);     // 4.50 * 200
        // surcharge = 0.50 × 0.325 × 200 = 32.5
        $this->assertSame(32.50, $q->fuelSurcharge);
    }

    public function test_fuel_surcharge_disabled_zeroes_out(): void
    {
        TransportSettings::current()->forceFill([
            'fuel_surcharge_enabled' => false,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        $this->assertSame(0.0, $q->fuelSurcharge);
    }

    public function test_minimum_charge_zero_when_subtotal_already_exceeds_minimum(): void
    {
        TransportSettings::current()->forceFill([
            'rate_per_km' => 20.00,    // 20 × 100 = 2000 — over minimum 800
            'minimum_charge' => 800.00,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        $this->assertSame(2000.0, $q->baseCost);
        $this->assertSame(0.0, $q->minimumAdjustment);
        // net = 2000 + 16.25 = 2016.25
        $this->assertSame(2016.25, $q->netTotal);
    }

    public function test_quotation_implements_wireable_round_trip(): void
    {
        // Bez Wireable Livewire 3 wywala "Property type not supported" gdy
        // Calculator page trzyma `public ?Quotation $quotation`. Round-trip
        // check chroni nas przed regresją.
        $q = new Quotation(
            distanceKm: 349.58,
            durationSeconds: 14005,
            rateUsed: 4.5,
            baseCost: 1573.1,
            fuelSurcharge: 0,
            minimumAdjustment: 0,
            netTotal: 1573.1,
            vatRate: 23,
            vatAmount: 361.81,
            grossTotal: 1934.91,
            currency: 'PLN',
            routingProvider: 'ors',
            polyline: 'abc_def',
        );

        $hydrated = Quotation::fromLivewire($q->toLivewire());

        $this->assertEquals($q, $hydrated);
    }

    public function test_currency_passes_through_from_settings(): void
    {
        TransportSettings::current()->forceFill(['currency' => 'EUR'])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        $this->assertSame('EUR', $q->currency);
    }

    private function setUpTransportSettingsTable(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }

    private function makeTransporterTenant(): Tenant
    {
        $plan = Plan::create([
            'code' => 'calc_test_'.uniqid(),
            'audience' => 'transporter',
            'name' => 'Test',
            'currency' => 'PLN',
            'limits' => ['routing_providers' => ['ors'], 'max_vehicles' => 5],
        ]);

        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'ors', 'api_key' => 'test-key'],
        ])->save();

        $tenant = Tenant::create([
            'slug' => 'calc-'.uniqid(),
            'name' => 'Calc',
            'type' => TenantType::Transporter,
            'db_name' => 'calc_'.uniqid(),
            'db_username' => 'calc_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $tenant->setRelation('plan', $plan);

        return $tenant;
    }
}
