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

class TransportPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_three_transport_plans(): void
    {
        TransportPlansSeeder::seed();

        $this->assertSame(3, Plan::query()->forTransporters()->count());
        $this->assertNotNull(Plan::query()->where('code', 'transport_solo')->first());
        $this->assertNotNull(Plan::query()->where('code', 'transport_pro')->first());
        $this->assertNotNull(Plan::query()->where('code', 'transport_fleet')->first());
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

    public function test_seeded_solo_plan_has_one_vehicle_limit(): void
    {
        TransportPlansSeeder::seed();

        $solo = Plan::query()->where('code', 'transport_solo')->first();
        $this->assertSame(1, $solo->limits['max_vehicles']);
        $this->assertSame(['ors'], $solo->limits['routing_providers']);
    }

    public function test_seeded_pro_plan_supports_mapbox(): void
    {
        TransportPlansSeeder::seed();

        $pro = Plan::query()->where('code', 'transport_pro')->first();
        $this->assertSame(5, $pro->limits['max_vehicles']);
        $this->assertContains('mapbox', $pro->limits['routing_providers']);
    }

    public function test_seeded_fleet_plan_is_unlimited(): void
    {
        TransportPlansSeeder::seed();

        $fleet = Plan::query()->where('code', 'transport_fleet')->first();
        $this->assertSame(-1, $fleet->limits['max_vehicles']);
        $this->assertContains('google', $fleet->limits['routing_providers']);
    }

    public function test_effective_limits_include_vehicles_and_drivers(): void
    {
        TransportPlansSeeder::seed();
        $plan = Plan::query()->where('code', 'transport_solo')->first();

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

        $this->assertSame(1, $limits['max_vehicles']);
        $this->assertSame(2, $limits['max_drivers']);
    }
}
