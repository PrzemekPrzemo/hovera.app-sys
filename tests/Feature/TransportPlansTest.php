<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Database\Seeders\TransportPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Marketing spec sync (2026-05-18): 3 stare plany (Solo/Pro/Fleet)
 * zastąpione 4 nowymi (Start/Pro/Business/Enterprise). Patrz
 * docs/TRANSPORT.md §15.5 + hovera.app/produkt/transport/.
 */
class TransportPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_four_transport_plans(): void
    {
        TransportPlansSeeder::seed();

        $this->assertSame(4, Plan::query()->forTransporters()->count());
        $this->assertNotNull(Plan::query()->where('code', 'transport_start')->first());
        $this->assertNotNull(Plan::query()->where('code', 'transport_pro')->first());
        $this->assertNotNull(Plan::query()->where('code', 'transport_business')->first());
        $this->assertNotNull(Plan::query()->where('code', 'transport_enterprise')->first());
    }

    public function test_plan_audience_scopes_segregate_correctly(): void
    {
        Plan::create([
            'code' => 'stable_only',
            'audience' => TenantType::Stable->value,
            'name' => 'Stable',
            'currency' => 'PLN',
        ]);
        Plan::create([
            'code' => 'transporter_only',
            'audience' => TenantType::Transporter->value,
            'name' => 'Transporter',
            'currency' => 'PLN',
        ]);

        $this->assertSame(1, Plan::query()->forStables()->count());
        $this->assertSame(1, Plan::query()->forTransporters()->count());
    }

    public function test_seeded_start_plan_has_four_vehicle_limit(): void
    {
        TransportPlansSeeder::seed();

        $start = Plan::query()->where('code', 'transport_start')->first();
        $this->assertSame(4, $start->limits['max_vehicles']);
        $this->assertSame(4, $start->limits['max_drivers']);
        $this->assertSame(['ors'], $start->limits['routing_providers']);
    }

    public function test_seeded_pro_plan_supports_mapbox(): void
    {
        TransportPlansSeeder::seed();

        $pro = Plan::query()->where('code', 'transport_pro')->first();
        $this->assertSame(12, $pro->limits['max_vehicles']);
        $this->assertContains('mapbox', $pro->limits['routing_providers']);
        $this->assertSame('most_popular', data_get($pro->features, 'highlight'));
    }

    public function test_seeded_business_plan_unlocks_unlimited_quotes(): void
    {
        TransportPlansSeeder::seed();

        $business = Plan::query()->where('code', 'transport_business')->first();
        $this->assertSame(25, $business->limits['max_vehicles']);
        $this->assertSame(-1, $business->limits['max_quotes_per_month']);
        $this->assertContains('google', $business->limits['routing_providers']);
    }

    public function test_seeded_enterprise_plan_is_unlimited_and_custom_priced(): void
    {
        TransportPlansSeeder::seed();

        $enterprise = Plan::query()->where('code', 'transport_enterprise')->first();
        $this->assertSame(-1, $enterprise->limits['max_vehicles']);
        $this->assertSame(-1, $enterprise->limits['max_drivers']);
        $this->assertTrue((bool) data_get($enterprise->features, 'is_custom_pricing'));
        $this->assertSame('contact_sales', data_get($enterprise->features, 'marketing_cta'));
    }

    public function test_seeder_is_idempotent(): void
    {
        TransportPlansSeeder::seed();
        TransportPlansSeeder::seed();

        $this->assertSame(4, Plan::query()->forTransporters()->count());
    }

    public function test_effective_limits_include_vehicles_and_drivers(): void
    {
        TransportPlansSeeder::seed();
        $plan = Plan::query()->where('code', 'transport_start')->first();

        $tenant = new Tenant([
            'slug' => 'test-trans-'.uniqid(),
            'name' => 'T',
            'type' => TenantType::Transporter,
            'db_name' => 'test_'.uniqid(),
            'db_username' => 'test_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'plan_id' => $plan->id,
        ]);
        $tenant->save();
        $tenant->setRelation('plan', $plan);

        $limits = $tenant->effectiveLimits();

        $this->assertSame(4, $limits['max_vehicles']);
        $this->assertSame(4, $limits['max_drivers']);
    }
}
