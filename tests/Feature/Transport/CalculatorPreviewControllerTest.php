<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\TenantType;
use App\Models\Central\FuelPrice;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\TransportSettings;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pokrywa POST /api/transport/calculator/preview — endpoint dla
 * live recalc JS w Calculator page. Patrz Calculator live UX
 * w docs/MARKETPLACE-ROADMAP.md.
 */
class CalculatorPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_calc_preview_').'.sqlite';
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
        $this->user = $this->makeOperatorUser($this->tenant);

        // Pre-populate fuel price + mock ORS — 100 km, 1h.
        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 7.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 100.0, 'duration' => 3600],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        // Mock geocoder bo Mapbox wymaga API key'a — w testach nie chcemy
        // konfigurować realnego tokena. Zwracamy stałe coords (52.0, 21.0).
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) {
            $m->shouldReceive('geocode')->andReturnUsing(function (string $address) {
                if (str_contains($address, 'NonExistent')) {
                    throw GeocodingException::notFound($address);
                }

                return new GeocodedAddress(
                    displayName: 'Mocked Address',
                    coords: new Coords(52.0, 21.0),
                );
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
        ]);

        $response->assertUnauthorized();
    }

    public function test_returns_full_quotation_with_coords(): void
    {
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
            'from_address' => 'Warszawa',
            'to_address' => 'Kraków',
            'loaded' => true,
            'horses_count' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'quotation' => [
                'distance_km', 'duration_seconds', 'rate_used',
                'base_cost', 'fuel_surcharge', 'net_total',
                'vat_amount', 'gross_total', 'currency',
                'routing_provider', 'polyline',
            ],
            'from' => ['address', 'lat', 'lng'],
            'to' => ['address', 'lat', 'lng'],
        ]);
        // JSON nie różnicuje float/int — sprawdzamy wartość loosely.
        $this->assertEquals(100.0, $response->json('quotation.distance_km'));
        $this->assertEquals(984.0, $response->json('quotation.gross_total'));
        $this->assertEquals(52.0, $response->json('from.lat'));
    }

    public function test_geocodes_when_only_addresses_provided(): void
    {
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_address' => 'Warszawa',
            'to_address' => 'Kraków',
        ]);

        $response->assertOk();
        $response->assertJsonPath('from.address', 'Mocked Address');
        $this->assertEquals(52.0, $response->json('from.lat'));
    }

    public function test_returns_422_when_geocoding_fails(): void
    {
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_address' => 'NonExistentXYZ',
            'to_lat' => 50.0, 'to_lng' => 19.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('field', 'from_address');
    }

    public function test_returns_422_when_no_address_and_no_coords(): void
    {
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'to_lat' => 50.0, 'to_lng' => 19.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('field', 'from_address');
    }

    public function test_round_trip_doubles_distance(): void
    {
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
            'calculation_mode' => 'round_trip',
        ]);

        $response->assertOk();
        $this->assertEquals(200.0, $response->json('quotation.distance_km'));
    }

    public function test_fixed_fees_and_surcharge_applied(): void
    {
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
            'fixed_fees' => [
                ['name' => 'Autostrada', 'amount' => 50.0],
                ['name' => '', 'amount' => 0], // defensive: filtrowane
            ],
            'surcharge_percent' => 10,
        ]);

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('quotation.fixed_fees_total'));
        $this->assertEquals(10.0, $response->json('quotation.surcharge_percent'));
        $this->assertCount(1, $response->json('quotation.fixed_fees'));
    }

    public function test_forbidden_for_driver_role(): void
    {
        // Demote to driver PRZED actingAsOperator — controller powinien 403.
        TenantMembership::query()->where('user_id', $this->user->id)
            ->update(['role' => 'driver']);
        $this->actingAsOperator();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
        ]);

        $response->assertForbidden();
    }

    public function test_no_tenant_in_session_returns_422(): void
    {
        // Brak tenanta na TenantManager → controller zwróci 422 z polskim
        // komunikatem "Brak aktywnego tenanta". Logujemy tylko user'a.
        $this->actingAs($this->user);

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_horses_count_is_clamped(): void
    {
        $this->actingAsOperator();

        // 999 powinno być zaklampowane do 30 (defensive).
        TransportSettings::current()->forceFill([
            'extra_horse_fee_default' => 100.0,
        ])->save();

        $response = $this->postJson('/api/transport/calculator/preview', [
            'from_lat' => 52.0, 'from_lng' => 21.0,
            'to_lat' => 50.0, 'to_lng' => 19.0,
            'horses_count' => 999,
        ]);

        $response->assertOk();
        $this->assertEquals(30, $response->json('quotation.horses_count'));
        // 100 zł × 29 (powyżej pierwszego) = 2900.
        $this->assertEquals(2900.0, $response->json('quotation.extra_horse_fee_total'));
    }

    /**
     * Loguje user'a + ustawia tenant na TenantManager singleton'ie.
     *
     * UWAGA: nie ustawiamy `current_tenant_id` w session bo wtedy
     * HydrateTenantConnectionFromSession middleware przekonfiguruje
     * tenant connection na MySQL (z Tenant::databaseConnectionConfig)
     * i nadpisze nasz SQLite. Ustawiamy private property bezpośrednio
     * przez reflection — wzór z OrderTransportTest.
     */
    private function actingAsOperator(): void
    {
        $this->actingAs($this->user);

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $this->tenant);
    }

    private function makeOperatorUser(Tenant $tenant): User
    {
        $user = User::create([
            'email' => 'operator-'.uniqid().'@example.test',
            'name' => 'Operator',
            'password' => bcrypt('secret123'),
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'operator',
            'joined_at' => now(),
        ]);

        return $user;
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
            'code' => 'calc_preview_test_'.uniqid(),
            'audience' => 'transporter',
            'name' => 'Test',
            'currency' => 'PLN',
            'limits' => ['routing_providers' => ['ors'], 'max_vehicles' => 5],
        ]);

        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'ors', 'api_key' => 'test-key'],
        ])->save();

        $tenant = Tenant::create([
            'slug' => 'calc-prv-'.uniqid(),
            'name' => 'Calc Preview',
            'type' => TenantType::Transporter,
            'db_name' => 'calc_prv_'.uniqid(),
            'db_username' => 'calc_prv_'.substr(uniqid(), -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $tenant->setRelation('plan', $plan);

        return $tenant;
    }
}
