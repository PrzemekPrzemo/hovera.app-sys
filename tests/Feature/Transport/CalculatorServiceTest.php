<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\CalculationMode;
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

    public function test_mode_one_way_does_not_double(): void
    {
        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(mode: CalculationMode::OneWay),
        );

        $this->assertSame(100.0, $q->distanceKm);
    }

    public function test_mode_round_trip_doubles_distance(): void
    {
        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(mode: CalculationMode::RoundTrip),
        );

        $this->assertSame(200.0, $q->distanceKm);
    }

    public function test_mode_return_home_adds_return_leg_distance(): void
    {
        TransportSettings::current()->forceFill([
            'home_address' => 'Baza, Warszawa',
            'home_lat' => 52.10,
            'home_lng' => 20.95,
        ])->save();

        // Mock ORS dla obu wywołań — Http::fake() już zwraca distance=100 dla *,
        // więc both A→B i B→base zwrócą 100 km. Suma = 200 km.
        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(mode: CalculationMode::ReturnHome),
        );

        $this->assertSame(200.0, $q->distanceKm);
    }

    public function test_mode_return_home_falls_back_to_round_trip_without_home_coords(): void
    {
        // home_* są nullable; bez wartości return_home musi spaść do *2.
        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(mode: CalculationMode::ReturnHome),
        );

        $this->assertSame(200.0, $q->distanceKm, 'falls back to *2 round_trip');
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
            extraHorseFeeTotal: 300.00,
            extraHorseFeePerHead: 150.00,
            horsesCount: 3,
            fixedFees: [['name' => 'Autostrada', 'amount' => 50.0]],
            fixedFeesTotal: 50.00,
            surchargePercent: 15.00,
            surchargeAmount: 245.00,
        );

        $hydrated = Quotation::fromLivewire($q->toLivewire());

        $this->assertEquals($q, $hydrated);
    }

    public function test_extra_horse_fee_zero_when_horses_count_is_one(): void
    {
        TransportSettings::current()->forceFill([
            'extra_horse_fee_default' => 150.00,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(horsesCount: 1),
        );

        // 1 koń = 0 dodatkowych = brak doliczenia, niezależnie od stawki.
        $this->assertSame(0.0, $q->extraHorseFeeTotal);
        $this->assertSame(150.00, $q->extraHorseFeePerHead);
        $this->assertSame(1, $q->horsesCount);
    }

    public function test_extra_horse_fee_charges_per_extra_horse(): void
    {
        TransportSettings::current()->forceFill([
            'extra_horse_fee_default' => 150.00,
            // Pump rate'y żeby base_cost przekroczył minimum_charge — łatwiej
            // zweryfikować net_total bez efektu minimum_adjustment.
            'rate_per_km' => 20.00,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(horsesCount: 4),
        );

        // 4 konie - 1 (w cenie bazowej) = 3 × 150 = 450
        $this->assertSame(450.00, $q->extraHorseFeeTotal);
        $this->assertSame(150.00, $q->extraHorseFeePerHead);
        $this->assertSame(4, $q->horsesCount);

        // net = base 2000 + fuel 16.25 + extra 450 = 2466.25; minimum (800)
        // już dawno przekroczone więc min_adjustment=0
        $this->assertSame(0.0, $q->minimumAdjustment);
        $this->assertSame(2466.25, $q->netTotal);
    }

    public function test_extra_horse_fee_disabled_when_default_is_zero(): void
    {
        // Domyślny stan TransportSettings: extra_horse_fee_default=0.
        // Niezależnie od liczby koni, cena = ta sama (legacy behaviour).
        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(horsesCount: 5),
        );

        $this->assertSame(0.0, $q->extraHorseFeeTotal);
    }

    public function test_extra_horse_fee_with_minimum_charge_adjustment(): void
    {
        // Sprawdzamy że extra_horse_fee wchodzi DO subtotalu PRZED
        // minimum_adjustment — gdy base+fuel+extra < min_charge, dobór
        // doliczy się do minimum (a nie minimum + extra).
        TransportSettings::current()->forceFill([
            'extra_horse_fee_default' => 50.00,
            'rate_per_km' => 3.00,           // base = 300
            'minimum_charge' => 1000.00,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(horsesCount: 3),
        );

        // base=300 + fuel=16.25 + extra=100 (2 dodatkowe × 50) = 416.25
        // min_adj = 1000 - 416.25 = 583.75 → net = 1000
        $this->assertSame(100.00, $q->extraHorseFeeTotal);
        $this->assertSame(583.75, $q->minimumAdjustment);
        $this->assertSame(1000.00, $q->netTotal);
    }

    public function test_fixed_fees_from_settings_default_are_added_to_subtotal(): void
    {
        TransportSettings::current()->forceFill([
            'rate_per_km' => 10.00,         // base = 1000
            'fixed_fees_default' => [
                ['name' => 'Autostrada A1', 'amount' => 150],
                ['name' => 'Prom Świnoujście', 'amount' => 250],
            ],
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        $this->assertSame(400.0, $q->fixedFeesTotal);
        $this->assertCount(2, $q->fixedFees);
        $this->assertSame('Autostrada A1', $q->fixedFees[0]['name']);

        // base=1000 + fuel=16.25 + fixed=400 = 1416.25 (over min 800)
        // net = 1416.25 + 0 (no surcharge default) = 1416.25
        $this->assertSame(0.0, $q->minimumAdjustment);
        $this->assertSame(1416.25, $q->netTotal);
    }

    public function test_fixed_fees_override_from_options_skips_settings_default(): void
    {
        TransportSettings::current()->forceFill([
            'fixed_fees_default' => [['name' => 'Default', 'amount' => 999]],
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(fixedFees: [['name' => 'Override', 'amount' => 100]]),
        );

        // Override z opcji wygrywa nad default'em z settings.
        $this->assertCount(1, $q->fixedFees);
        $this->assertSame('Override', $q->fixedFees[0]['name']);
        $this->assertSame(100.0, $q->fixedFeesTotal);
    }

    public function test_fixed_fees_empty_array_override_disables_defaults(): void
    {
        TransportSettings::current()->forceFill([
            'fixed_fees_default' => [['name' => 'Default', 'amount' => 999]],
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(fixedFees: []),
        );

        $this->assertSame(0.0, $q->fixedFeesTotal);
        $this->assertSame([], $q->fixedFees);
    }

    public function test_fixed_fees_skip_invalid_entries(): void
    {
        TransportSettings::current()->forceFill([
            'fixed_fees_default' => [
                ['name' => 'Valid', 'amount' => 50],
                ['name' => '', 'amount' => 100],         // empty name → skip
                ['name' => 'Negative', 'amount' => -10], // negative → skip
                'not-an-array',                          // garbage → skip
            ],
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        $this->assertCount(1, $q->fixedFees);
        $this->assertSame(50.0, $q->fixedFeesTotal);
    }

    public function test_surcharge_percent_from_settings_applied_after_minimum_adjustment(): void
    {
        TransportSettings::current()->forceFill([
            'rate_per_km' => 10.00,             // base = 1000
            'minimum_charge' => 0,              // disable min adjustment
            'surcharge_percent_default' => 20,  // +20% margin
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        // costs = base 1000 + fuel 16.25 = 1016.25
        // surcharge = 1016.25 × 0.20 = 203.25
        // net = 1016.25 + 203.25 = 1219.50
        $this->assertSame(20.0, $q->surchargePercent);
        $this->assertSame(203.25, $q->surchargeAmount);
        $this->assertSame(1219.50, $q->netTotal);
    }

    public function test_surcharge_includes_minimum_adjustment_in_base(): void
    {
        TransportSettings::current()->forceFill([
            'rate_per_km' => 1.00,           // base = 100 (poniżej min 800)
            'minimum_charge' => 800,
            'surcharge_percent_default' => 10,
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
        );

        // costs = base 100 + fuel 16.25 = 116.25
        // min_adj = 800 - 116.25 = 683.75
        // costs+min = 800
        // surcharge = 800 × 0.10 = 80
        // net = 800 + 80 = 880
        $this->assertSame(683.75, $q->minimumAdjustment);
        $this->assertSame(80.0, $q->surchargeAmount);
        $this->assertSame(880.0, $q->netTotal);
    }

    public function test_surcharge_explicit_zero_overrides_settings_default(): void
    {
        TransportSettings::current()->forceFill([
            'surcharge_percent_default' => 25,  // settings = 25%
        ])->save();

        $q = app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(surchargePercent: 0.0),  // opt-out
        );

        $this->assertSame(0.0, $q->surchargePercent);
        $this->assertSame(0.0, $q->surchargeAmount);
    }

    public function test_vehicle_weight_kg_and_height_cm_propagate_as_tons_and_meters_to_ors(): void
    {
        // Override ORS mock żeby przechwycić body i sprawdzić konwersję jednostek.
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 100.0, 'duration' => 3600],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        app(CalculatorService::class)->calculate(
            $this->tenant,
            new Coords(52.0, 21.0),
            new Coords(50.0, 19.0),
            new CalculationOptions(
                vehicleGrossWeightKg: 7500,  // → 7.5 t
                vehicleHeightCm: 380,         // → 3.8 m
            ),
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'driving-hgv')) {
                return false;
            }
            $body = json_decode($request->body(), true);

            return ($body['options']['profile_params']['restrictions']['weight'] ?? null) === 7.5
                && ($body['options']['profile_params']['restrictions']['height'] ?? null) === 3.8;
        });
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
            $t->decimal('extra_horse_fee_default', 8, 2)->default(0);
            $t->json('fixed_fees_default')->nullable();
            $t->decimal('surcharge_percent_default', 5, 2)->nullable();
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->string('home_address', 255)->nullable();
            $t->decimal('home_lat', 10, 7)->nullable();
            $t->decimal('home_lng', 10, 7)->nullable();
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
