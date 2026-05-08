<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\PlanAddon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_has_many_addons_ordered_by_sort_order(): void
    {
        $plan = Plan::create([
            'code' => 'pro',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);

        PlanAddon::create([
            'plan_id' => $plan->id,
            'code' => 'horses_plus_50',
            'name' => '+50 koni',
            'resource_type' => 'horses',
            'quantity' => 50,
            'price_monthly_cents' => 19900,
            'sort_order' => 20,
        ]);

        PlanAddon::create([
            'plan_id' => $plan->id,
            'code' => 'horses_plus_10',
            'name' => '+10 koni',
            'resource_type' => 'horses',
            'quantity' => 10,
            'price_monthly_cents' => 5000,
            'sort_order' => 10,
        ]);

        $addons = $plan->fresh()->addons;

        $this->assertCount(2, $addons);
        $this->assertSame('horses_plus_10', $addons[0]->code);
        $this->assertSame('horses_plus_50', $addons[1]->code);
    }

    public function test_addon_code_is_unique_per_plan_but_can_repeat_across_plans(): void
    {
        $a = Plan::create(['code' => 'a', 'name' => 'A', 'currency' => 'PLN']);
        $b = Plan::create(['code' => 'b', 'name' => 'B', 'currency' => 'PLN']);

        PlanAddon::create([
            'plan_id' => $a->id,
            'code' => 'horses_plus_10',
            'name' => '+10 koni',
            'quantity' => 10,
            'price_monthly_cents' => 5000,
        ]);

        // Same code on another plan: allowed.
        PlanAddon::create([
            'plan_id' => $b->id,
            'code' => 'horses_plus_10',
            'name' => '+10 koni',
            'quantity' => 10,
            'price_monthly_cents' => 4000,
        ]);

        $this->assertSame(2, PlanAddon::query()->where('code', 'horses_plus_10')->count());

        // Same code on same plan: rejected by unique index.
        $this->expectException(QueryException::class);
        PlanAddon::create([
            'plan_id' => $a->id,
            'code' => 'horses_plus_10',
            'name' => 'duplicate',
            'quantity' => 10,
            'price_monthly_cents' => 5000,
        ]);
    }

    public function test_addon_belongs_to_plan(): void
    {
        $plan = Plan::create(['code' => 'free', 'name' => 'Free', 'currency' => 'PLN']);

        $addon = PlanAddon::create([
            'plan_id' => $plan->id,
            'code' => 'horses_plus_5',
            'name' => '+5 koni',
            'quantity' => 5,
            'price_monthly_cents' => 2500,
        ]);

        $this->assertSame($plan->id, $addon->plan->id);
    }
}
