<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Enums\TenantType;
use App\Filament\App\Widgets\QuickStartWidget;
use App\Filament\App\Widgets\TodayStatsWidget;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Services\Dashboard\TodayDashboardService;
use App\Tenancy\TenantManager;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression guard: vet (i inne role spoza FINANCE_STAFF) NIE może widzieć
 * informacji finansowych na dashboardzie /app.
 *
 * Konkretnie:
 *  - `TodayStatsWidget` ma 4. kafelek "Nieopłacone faktury (kwota zł)" —
 *    widoczny tylko dla owner/admin/manager/viewer.
 *  - `QuickStartWidget` pokazuje kartę onboardingu KSeF — tylko dla
 *    FINANCE_STAFF (inni i tak nie mają canAccess KsefSettings, ale link
 *    na dashboard'zie wprowadzałby w błąd).
 *
 * Background: TenantRoleGate::FINANCE_STAFF = ['owner', 'admin', 'manager',
 * 'viewer']. Role spoza tej listy (instructor, employee, vet) NIE widzą
 * `InvoiceResource`/`KsefSettings` — ale widgety wcześniej pomijały gate.
 */
class VetCannotSeeFinanceWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_stats_hides_unpaid_invoices_tile_for_vet(): void
    {
        $stable = $this->makeStable();
        [$vet] = $this->makeUserWithRole($stable, 'vet');

        $this->actingAs($vet);
        app(TenantManager::class)->setCurrent($stable);
        $this->mockDashboardService(unpaidCount: 5, unpaidCents: 12_345_00);

        $stats = $this->invokeGetStats(new TodayStatsWidget);

        $this->assertCount(3, $stats, 'Vet should see only 3 tiles (no unpaid invoices).');
        $this->assertNoStatContains($stats, '12 345');  // kwota PLN
        $this->assertNoStatContains($stats, 'unpaid_invoices');
    }

    public function test_today_stats_shows_unpaid_invoices_tile_for_owner(): void
    {
        $stable = $this->makeStable();
        [$owner] = $this->makeUserWithRole($stable, 'owner');

        $this->actingAs($owner);
        app(TenantManager::class)->setCurrent($stable);
        $this->mockDashboardService(unpaidCount: 5, unpaidCents: 12_345_00);

        $stats = $this->invokeGetStats(new TodayStatsWidget);

        $this->assertCount(4, $stats, 'Owner should see all 4 tiles including unpaid invoices.');
    }

    public function test_today_stats_hides_unpaid_invoices_tile_for_instructor(): void
    {
        $stable = $this->makeStable();
        [$instructor] = $this->makeUserWithRole($stable, 'instructor');

        $this->actingAs($instructor);
        app(TenantManager::class)->setCurrent($stable);
        $this->mockDashboardService(unpaidCount: 5, unpaidCents: 12_345_00);

        $stats = $this->invokeGetStats(new TodayStatsWidget);

        $this->assertCount(3, $stats);
    }

    public function test_quick_start_hides_ksef_slot_for_vet(): void
    {
        $stable = $this->makeStable();
        [$vet] = $this->makeUserWithRole($stable, 'vet');

        $this->actingAs($vet);
        app(TenantManager::class)->setCurrent($stable);

        $widget = new QuickStartWidget;
        $slots = $widget->getSlots();
        $keys = collect($slots)->pluck('key')->all();

        $this->assertNotContains('ksef', $keys, 'Vet should not see KSeF onboarding card on dashboard.');
    }

    public function test_quick_start_shows_ksef_slot_for_owner(): void
    {
        $stable = $this->makeStable();
        [$owner] = $this->makeUserWithRole($stable, 'owner');

        $this->actingAs($owner);
        app(TenantManager::class)->setCurrent($stable);

        $widget = new QuickStartWidget;
        $slots = $widget->getSlots();
        $keys = collect($slots)->pluck('key')->all();

        $this->assertContains('ksef', $keys, 'Owner should see KSeF onboarding card when cert missing.');
    }

    /**
     * @return list<Stat>
     */
    private function invokeGetStats(TodayStatsWidget $widget): array
    {
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    /**
     * @param  list<mixed>  $stats
     */
    private function assertNoStatContains(array $stats, string $needle): void
    {
        foreach ($stats as $stat) {
            $this->assertStringNotContainsString(
                $needle,
                (string) $stat->getValue().' '.(string) $stat->getDescription(),
                "Found leaked finance content `{$needle}` in dashboard stat",
            );
        }
    }

    private function mockDashboardService(int $unpaidCount, int $unpaidCents): void
    {
        $mock = Mockery::mock(TodayDashboardService::class);
        $mock->shouldReceive('snapshot')->andReturn([
            'bookings_today' => 3,
            'vacant_boxes' => 2,
            'overdue_care' => 1,
            'unpaid_invoices_count' => $unpaidCount,
            'unpaid_invoices_total_cents' => $unpaidCents,
        ]);
        $mock->shouldReceive('trend')->andReturn([
            'bookings_today' => [1, 2, 2, 3, 1, 2, 3],
            'overdue_care' => [0, 0, 1, 1, 1, 0, 1],
            'unpaid_invoices_count' => [4, 4, 5, 5, 5, 5, $unpaidCount],
            'unpaid_invoices_total_cents' => [1000_00, 1000_00, 1200_00, 1200_00, 1200_00, 1200_00, $unpaidCents],
        ]);
        $this->app->instance(TodayDashboardService::class, $mock);
    }

    private function makeStable(): Tenant
    {
        $plan = Plan::firstOrCreate(['code' => 'pro'], [
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);

        return Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia Test',
            'type' => TenantType::Stable,
            'plan_id' => $plan->id,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    /**
     * @return array{0: User, 1: TenantMembership}
     */
    private function makeUserWithRole(Tenant $stable, string $role): array
    {
        $user = User::create([
            'email' => $role.'-'.uniqid().'@test.pl',
            'name' => ucfirst($role).' Test',
            'password' => bcrypt('secret123'),
        ]);
        $membership = TenantMembership::create([
            'tenant_id' => $stable->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        return [$user, $membership];
    }
}
