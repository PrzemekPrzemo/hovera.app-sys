<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Master\MasterMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MasterMetricsTest extends TestCase
{
    use RefreshDatabase;

    private MasterMetrics $m;

    private Plan $monthlyPlan;

    private Plan $yearlyPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = $this->app->make(MasterMetrics::class);

        $this->monthlyPlan = Plan::create([
            'code' => 'stable',
            'name' => 'Stable',
            'currency' => 'PLN',
            'price_monthly_cents' => 9900,
            'price_yearly_cents' => 99000,
            'is_active' => true,
            'is_public' => true,
        ]);
        $this->yearlyPlan = Plan::create([
            'code' => 'pro',
            'name' => 'Pro',
            'currency' => 'PLN',
            'price_monthly_cents' => 19900,
            'price_yearly_cents' => 199000,
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    public function test_tenant_counts_by_status_returns_zero_for_missing_buckets(): void
    {
        $this->makeTenant('active');
        $this->makeTenant('active');
        $this->makeTenant('trialing');

        $counts = $this->m->tenantCountsByStatus();

        $this->assertSame(2, $counts['active']);
        $this->assertSame(1, $counts['trialing']);
        $this->assertSame(0, $counts['churned']);
        $this->assertSame(0, $counts['suspended']);
    }

    public function test_mrr_sums_monthly_subscriptions(): void
    {
        $a = $this->makeTenant('active');
        $b = $this->makeTenant('active');
        $this->makeSubscription($a, $this->monthlyPlan, 'monthly');
        $this->makeSubscription($b, $this->monthlyPlan, 'monthly');

        // 9900 + 9900 = 19800 grosze
        $this->assertSame(19800, $this->m->mrrCents());
        $this->assertSame(19800 * 12, $this->m->arrCents());
    }

    public function test_mrr_amortises_yearly_subscriptions(): void
    {
        $tenant = $this->makeTenant('active');
        $this->makeSubscription($tenant, $this->yearlyPlan, 'yearly');

        // 199000 / 12 = 16583.33 → rounded to 16583
        $this->assertSame(16583, $this->m->mrrCents());
    }

    public function test_mrr_excludes_cancelled_subscriptions(): void
    {
        $tenant = $this->makeTenant('active');
        $this->makeSubscription($tenant, $this->monthlyPlan, 'monthly', status: 'cancelled');

        $this->assertSame(0, $this->m->mrrCents());
    }

    public function test_churn_rate_is_zero_when_nothing_to_divide_by(): void
    {
        $this->assertSame(0.0, $this->m->churnRate(30));
    }

    public function test_churn_rate_counts_recently_churned(): void
    {
        // 4 mature tenants — 1 churned in window, 3 still active
        for ($i = 0; $i < 3; $i++) {
            $this->makeTenant('active', createdAt: now()->subMonths(2));
        }
        $churned = $this->makeTenant('active', createdAt: now()->subMonths(2));

        // Travel forward, mark one churned
        $churned->forceFill(['status' => 'churned'])->save();

        $rate = $this->m->churnRate(30);

        $this->assertGreaterThan(0.0, $rate);
        $this->assertLessThanOrEqual(1.0, $rate);
    }

    public function test_recently_active_tenants_orders_by_last_activity(): void
    {
        $stale = $this->makeTenant('active');
        $stale->forceFill(['last_activity_at' => now()->subDays(20)])->save();

        $fresh = $this->makeTenant('active');
        $fresh->forceFill(['last_activity_at' => now()->subHour()])->save();

        $list = $this->m->recentlyActiveTenants(10);

        $this->assertSame($fresh->id, $list->first()->id);
    }

    public function test_stale_tenants_returns_only_quiet_accounts(): void
    {
        $hot = $this->makeTenant('active');
        $hot->forceFill(['last_activity_at' => now()->subHour()])->save();

        $cold = $this->makeTenant('active');
        $cold->forceFill(['last_activity_at' => now()->subMonth()])->save();

        $never = $this->makeTenant('active');
        $never->forceFill(['last_activity_at' => null])->save();

        $stale = $this->m->staleTenants(sinceDays: 14)->pluck('id')->all();

        $this->assertContains($cold->id, $stale);
        $this->assertContains($never->id, $stale);
        $this->assertNotContains($hot->id, $stale);
    }

    public function test_live_health_signals_for_thriving_tenant(): void
    {
        $t = $this->makeTenant('active', createdAt: now()->subMonths(2));
        $t->forceFill(['last_activity_at' => now()->subDay()])->save();

        $h = $this->m->liveHealth($t);

        // active 50 + recent 30 + mature 20 = 100
        $this->assertSame(100, $h['score']);
        $this->assertTrue($h['signals']['active']);
        $this->assertTrue($h['signals']['recent_activity']);
        $this->assertTrue($h['signals']['mature']);
    }

    public function test_live_health_punishes_suspended(): void
    {
        $t = $this->makeTenant('suspended');

        $h = $this->m->liveHealth($t);

        $this->assertSame(0, $h['score']);   // clamped from -50
        $this->assertTrue($h['signals']['suspended']);
    }

    public function test_format_cents_polish_locale(): void
    {
        $this->assertSame('1 234,56 PLN', $this->m->formatCents(123456));
        $this->assertSame('99,00 PLN', $this->m->formatCents(9900));
    }

    private function makeTenant(string $status, ?Carbon $createdAt = null): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'mm-'.$u,
            'name' => 'Test '.$u,
            'db_name' => 'mm_'.$u,
            'db_username' => 'mm_'.substr($u, -8),
            'status' => $status,
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        if ($createdAt) {
            $t->forceFill(['created_at' => $createdAt])->save();
        }

        return $t;
    }

    private function makeSubscription(
        Tenant $tenant,
        Plan $plan,
        string $cycle,
        string $status = 'active',
    ): Subscription {
        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => $cycle,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);
    }
}
