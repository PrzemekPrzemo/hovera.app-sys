<?php

declare(strict_types=1);

namespace Tests\Feature\Trial;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\PlanLimitChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Trial 2.0 — verify effective limit logic and PlanLimitChecker
 * smoke-paths. End-to-end Horse/Client creation against a real
 * tenant DB is covered by the existing tenant-aware tests; here
 * we exercise the policy in isolation.
 */
class TrialLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(string $code, array $limits): Plan
    {
        return Plan::create([
            'code' => $code,
            'name' => ucfirst($code),
            'currency' => 'PLN',
            'price_monthly_cents' => 10000,
            'price_yearly_cents' => 100000,
            'limits' => $limits,
            'features' => [],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);
    }

    private function makeTenant(Plan $plan, string $status = 'trialing', ?int $trialMaxHorses = 10, ?int $trialMaxClients = 5): Tenant
    {
        $tenant = new Tenant([
            'slug' => 'acme-'.uniqid(),
            'name' => 'Acme',
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'plan_id' => $plan->id,
            'status' => $status,
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'hovera_t_'.uniqid(),
            'db_username' => 'hovera_t_'.uniqid(),
            'trial_ends_at' => Carbon::now()->addDays(30),
            'trial_max_horses' => $trialMaxHorses,
            'trial_max_clients' => $trialMaxClients,
        ]);
        $tenant->db_password = 'PASSWORD123456789';
        $tenant->save();

        return $tenant->fresh(['plan']);
    }

    public function test_effective_limits_use_trial_caps_when_trialing(): void
    {
        $pro = $this->makePlan('pro', [
            'max_horses' => 100,
            'max_clients' => -1,
            'max_users' => -1,
            'max_storage_mb' => 50000,
        ]);
        $tenant = $this->makeTenant($pro, status: 'trialing', trialMaxHorses: 10, trialMaxClients: 5);

        $limits = $tenant->effectiveLimits();

        $this->assertSame(10, $limits['max_horses']);
        $this->assertSame(5, $limits['max_clients']);
        // Storage / users powinny zostać brane z planu Pro.
        $this->assertSame(-1, $limits['max_users']);
        $this->assertSame(50000, $limits['max_storage_mb']);
    }

    public function test_effective_limits_fall_back_to_plan_when_active(): void
    {
        $pro = $this->makePlan('pro', [
            'max_horses' => 100,
            'max_clients' => -1,
            'max_users' => -1,
            'max_storage_mb' => 50000,
        ]);
        $tenant = $this->makeTenant($pro, status: 'active', trialMaxHorses: 10, trialMaxClients: 5);

        $limits = $tenant->effectiveLimits();

        // Trial caps są ignorowane gdy status != trialing.
        $this->assertSame(100, $limits['max_horses']);
        $this->assertSame(-1, $limits['max_clients']);
    }

    public function test_effective_limits_no_trial_overrides_use_plan(): void
    {
        $solo = $this->makePlan('solo', [
            'max_horses' => 10,
            'max_clients' => 30,
            'max_users' => 1,
            'max_storage_mb' => 2000,
        ]);
        $tenant = $this->makeTenant($solo, status: 'trialing', trialMaxHorses: null, trialMaxClients: null);

        $limits = $tenant->effectiveLimits();

        // Brak trial cap → bierzemy plan limit nawet w trialu.
        $this->assertSame(10, $limits['max_horses']);
        $this->assertSame(30, $limits['max_clients']);
    }

    public function test_unlimited_plan_never_blocks_resource_addition(): void
    {
        $enterprise = $this->makePlan('enterprise', [
            'max_horses' => -1,
            'max_clients' => -1,
            'max_users' => -1,
            'max_storage_mb' => -1,
        ]);
        $tenant = $this->makeTenant($enterprise, status: 'active', trialMaxHorses: null, trialMaxClients: null);

        $checker = new PlanLimitChecker;

        // -1 = unlimited — checker zwraca natychmiast bez query do tenant DB.
        $checker->assertCanAddHorse($tenant);
        $checker->assertCanAddClient($tenant);

        $this->assertTrue(true); // smoke
    }

    public function test_plan_limit_exceeded_message_is_polish(): void
    {
        app()->setLocale('pl');

        $e = PlanLimitExceeded::horses(10);
        $this->assertStringContainsString('Trial', $e->getMessage());
        $this->assertStringContainsString('10', $e->getMessage());
        $this->assertSame('horse', $e->resource);
        $this->assertSame(10, $e->limit);
    }
}
