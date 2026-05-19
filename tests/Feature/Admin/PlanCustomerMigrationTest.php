<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Pages\LegacyPlanMigration;
use App\Models\Central\AuditLogMaster;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Notifications\TenantPlanMigratedNotification;
use App\Services\Billing\LegacyPlanMigrator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Migracja klientów z legacy planów — pełna ścieżka:
 *  1) lista pokazuje tylko legacy tenantów
 *  2) per-row migrate przepisuje plan_id, audyt, email
 *  3) bulk migrate iteruje
 *  4) idempotencja (re-run = no-op)
 */
class PlanCustomerMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Notification::fake();
    }

    public function test_page_shows_only_tenants_on_legacy_plans(): void
    {
        $this->actingAsMasterAdmin();

        [$legacyPlan, $newPlan] = $this->makeLegacyAndNewPlans();
        $legacyTenant = $this->makeTenant(planId: $legacyPlan->id);
        $newTenant = $this->makeTenant(planId: $newPlan->id);

        Livewire::test(LegacyPlanMigration::class)
            ->assertCanSeeTableRecords([$legacyTenant])
            ->assertCanNotSeeTableRecords([$newTenant]);
    }

    public function test_per_row_migrate_swaps_plan_id_and_audits(): void
    {
        $this->actingAsMasterAdmin();
        [$legacyPlan, $newPlan] = $this->makeLegacyAndNewPlans();
        $tenant = $this->makeTenant(planId: $legacyPlan->id);
        $this->attachOwner($tenant);

        Livewire::test(LegacyPlanMigration::class)
            ->callTableAction('migrate', $tenant, [
                'effective' => 'next_cycle',
                'reason' => 'unit test',
                'send_email' => true,
            ]);

        $tenant->refresh();
        $this->assertSame($newPlan->id, $tenant->plan_id);

        $audit = AuditLogMaster::query()
            ->where('action', 'plan.legacy_migrated')
            ->where('target_id', $tenant->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($legacyPlan->code, $audit->payload['old_plan_code']);
        $this->assertSame($newPlan->code, $audit->payload['new_plan_code']);
        $this->assertSame('unit test', $audit->payload['reason']);

        Notification::assertSentOnDemand(TenantPlanMigratedNotification::class);
    }

    public function test_bulk_migrate_runs_for_all_records(): void
    {
        $this->actingAsMasterAdmin();
        [$legacyPlan, $newPlan] = $this->makeLegacyAndNewPlans();
        $tenants = collect();
        for ($i = 0; $i < 3; $i++) {
            $tenants->push($this->makeTenant(planId: $legacyPlan->id));
        }

        Livewire::test(LegacyPlanMigration::class)
            ->callTableBulkAction('bulk_migrate', $tenants->all(), [
                'effective' => 'next_cycle',
                'send_email' => false,
            ]);

        $migratedCount = Tenant::query()
            ->whereIn('id', $tenants->pluck('id'))
            ->where('plan_id', $newPlan->id)
            ->count();
        $this->assertSame(3, $migratedCount);
    }

    public function test_migrate_is_idempotent_when_already_on_target(): void
    {
        [$legacyPlan, $newPlan] = $this->makeLegacyAndNewPlans();
        $tenant = $this->makeTenant(planId: $newPlan->id); // już na nowym

        $migrator = app(LegacyPlanMigrator::class);
        $result = $migrator->migrate($tenant, $newPlan);

        $this->assertFalse($result['changed']);
    }

    public function test_recommended_plan_returns_null_for_non_legacy(): void
    {
        [$legacyPlan, $newPlan] = $this->makeLegacyAndNewPlans();
        $tenant = $this->makeTenant(planId: $newPlan->id);

        $this->assertNull(app(LegacyPlanMigrator::class)->recommendedNewPlan($tenant));
    }

    /**
     * @return array{0:Plan,1:Plan}
     */
    private function makeLegacyAndNewPlans(): array
    {
        $legacy = Plan::create([
            'code' => 'transport_solo_legacy',
            'name' => 'Solo Legacy',
            'audience' => TenantType::Transporter->value,
            'currency' => 'PLN',
            'price_monthly_cents' => 14900,
            'price_yearly_cents' => 161000,
            'sort_order' => 90,
            'is_active' => false,
            'is_public' => false,
        ]);
        $new = Plan::create([
            'code' => 'transport_start',
            'name' => 'Transport Start',
            'audience' => TenantType::Transporter->value,
            'currency' => 'PLN',
            'price_monthly_cents' => 25000,
            'price_yearly_cents' => 270000,
            'sort_order' => 110,
            'is_active' => true,
            'is_public' => true,
        ]);

        return [$legacy, $new];
    }

    private function makeTenant(string $planId): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'mt-'.$u,
            'name' => 'Migr Tenant '.$u,
            'type' => TenantType::Transporter,
            'db_name' => 'mt_'.$u,
            'db_username' => 'mt_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'plan_id' => $planId,
            'status' => 'active',
        ]);
    }

    private function attachOwner(Tenant $tenant): User
    {
        $user = User::create([
            'email' => 'owner-'.uniqid().'@example.com',
            'name' => 'Owner',
            'password' => Hash::make('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'mig-admin-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }
}
