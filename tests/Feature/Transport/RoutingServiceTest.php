<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Domain\Transport\Routing\RoutingService;
use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\RouteCache;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_routing_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
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

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_returns_ors_provider_for_default_settings(): void
    {
        TransportSettings::current(); // tworzy default singleton z provider=ors
        $tenant = $this->makeTransporterTenant(['ors']);

        $provider = app(RoutingService::class)->for($tenant);

        $this->assertSame('ors', $provider->id());
    }

    public function test_uses_mapbox_when_settings_say_mapbox_and_plan_allows(): void
    {
        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'mapbox', 'api_key' => 'pk.tenant-specific'],
        ])->save();

        $tenant = $this->makeTransporterTenant(['ors', 'mapbox']);

        $provider = app(RoutingService::class)->for($tenant);
        $this->assertSame('mapbox', $provider->id());
    }

    public function test_blocks_provider_when_plan_does_not_allow(): void
    {
        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'google'],
        ])->save();

        // Plan zawiera tylko ors i mapbox — google forbidden
        $tenant = $this->makeTransporterTenant(['ors', 'mapbox']);

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('does not allow routing provider [google]');

        app(RoutingService::class)->for($tenant);
    }

    public function test_calculate_caches_result_in_route_cache(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 295.5, 'duration' => 13_500],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'ors', 'api_key' => 'k-tenant'],
        ])->save();

        $tenant = $this->makeTransporterTenant(['ors']);
        $from = new Coords(52.2297, 21.0122);
        $to = new Coords(50.0647, 19.9450);

        $first = app(RoutingService::class)->calculate($tenant, $from, $to);

        $this->assertSame(295.5, $first->distanceKm);
        $this->assertSame(1, RouteCache::count());

        // Drugie wywołanie — z cache, bez HTTP call.
        Http::fake([]); // resetujemy fake — cokolwiek pójdzie do HTTP w tym wywołaniu, fail.
        Http::preventStrayRequests();

        $second = app(RoutingService::class)->calculate($tenant, $from, $to);

        $this->assertSame(295.5, $second->distanceKm);
        $this->assertSame(1, RouteCache::count(), 'cache must remain at 1 row');
    }

    public function test_cache_key_differentiates_providers_and_profiles(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [['summary' => ['distance' => 100.0, 'duration' => 3600], 'geometry' => 'X']],
            ]),
        ]);

        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'ors', 'api_key' => 'k'],
        ])->save();

        $tenant = $this->makeTransporterTenant(['ors']);
        $from = new Coords(52.0, 21.0);
        $to = new Coords(50.0, 20.0);

        app(RoutingService::class)->calculate($tenant, $from, $to, new RouteOptions(profile: 'truck'));
        app(RoutingService::class)->calculate($tenant, $from, $to, new RouteOptions(profile: 'car'));

        $this->assertSame(2, RouteCache::count(), 'different profiles must produce separate cache rows');
    }

    private function makeTransporterTenant(array $allowedProviders): Tenant
    {
        $plan = Plan::create([
            'code' => 'transport_test_'.uniqid(),
            'audience' => 'transporter',
            'name' => 'Test',
            'currency' => 'PLN',
            'limits' => ['routing_providers' => $allowedProviders, 'max_vehicles' => 5],
        ]);

        $tenant = Tenant::create([
            'slug' => 'rt-'.uniqid(),
            'name' => 'RT',
            'type' => TenantType::Transporter,
            'db_name' => 'rt_'.uniqid(),
            'db_username' => 'rt_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $tenant->setRelation('plan', $plan);

        return $tenant;
    }
}
