<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Enums\TenantType;
use App\Filament\App\Pages\TransportEntry;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Free plan stable: nav item ukryty + canAccess()=false. Bezpośredni URL
 * hit zwraca redirect (mount → redirect na billing), nie 200.
 *
 * Tu testujemy unitowo logikę gate'u — pełny middleware integration test
 * wymagałby session+tenant pipeline który InitialiseTenantFromSessionTest
 * obsługuje osobno.
 */
class FreePlanCannotAccessTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_hidden_for_free_plan(): void
    {
        $stable = $this->makeStable('free');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertFalse(TransportEntry::shouldRegisterNavigation());
    }

    public function test_can_access_false_for_free_plan(): void
    {
        $stable = $this->makeStable('free');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertFalse(TransportEntry::canAccess());
    }

    public function test_can_access_true_for_paid_plan(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertTrue(TransportEntry::canAccess());
    }

    public function test_mount_redirects_to_billing_for_free_plan(): void
    {
        $stable = $this->makeStable('free');
        app(TenantManager::class)->setCurrent($stable);

        $page = new TransportEntry;
        $response = $page->mount();

        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('billing', $response->getTargetUrl());
    }

    public function test_mount_returns_null_for_paid_plan(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $page = new TransportEntry;
        $this->assertNull($page->mount());
    }

    private function makeStable(string $planCode): Tenant
    {
        $plan = Plan::firstOrCreate(['code' => $planCode], [
            'audience' => 'stable',
            'name' => ucfirst($planCode),
            'currency' => 'PLN',
        ]);

        return Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia',
            'type' => TenantType::Stable,
            'plan_id' => $plan->id,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
