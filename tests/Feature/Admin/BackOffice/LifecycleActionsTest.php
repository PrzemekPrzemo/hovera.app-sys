<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\BackOffice;

use App\Filament\Admin\Resources\TenantResource\Pages\ListTenants;
use App\Models\Central\AuditLogMaster;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class LifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_change_plan_records_audit_and_updates_tenant(): void
    {
        $admin = $this->actingAsMasterAdmin();
        $tenant = $this->makeTenant();
        $oldPlan = Plan::create([
            'code' => 'starter', 'name' => 'Starter', 'currency' => 'PLN',
            'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'sort_order' => 1,
        ]);
        $newPlan = Plan::create([
            'code' => 'pro', 'name' => 'Pro', 'currency' => 'PLN',
            'price_monthly_cents' => 9900, 'price_yearly_cents' => 99000, 'sort_order' => 2,
        ]);
        $tenant->forceFill(['plan_id' => $oldPlan->id])->save();

        Livewire::test(ListTenants::class)
            ->callTableAction('change_plan', $tenant, data: [
                'plan_id' => $newPlan->id,
                'period' => 'monthly',
                'reason' => 'klient prosil o upgrade',
            ])
            ->assertHasNoTableActionErrors();

        $tenant->refresh();
        $this->assertSame($newPlan->id, $tenant->plan_id);
        $this->assertSame(1, AuditLogMaster::query()->where('action', 'tenant.plan.change')->count());
    }

    public function test_force_password_reset_sends_link(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'owner@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $this->actingAsMasterAdmin();
        $tenant = $this->makeTenant();
        $owner = User::create([
            'email' => 'owner@example.com',
            'name' => 'Jan',
            'password' => Hash::make('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        Livewire::test(ListTenants::class)
            ->callTableAction('force_password_reset', $tenant)
            ->assertHasNoTableActionErrors();

        $audit = AuditLogMaster::query()->where('action', 'tenant.owner.force_password_reset')->first();
        $this->assertNotNull($audit);
        $this->assertSame($tenant->id, $audit->tenant_id);
    }

    public function test_suspend_and_reactivate_round_trip(): void
    {
        $this->actingAsMasterAdmin();
        $tenant = $this->makeTenant();
        $this->assertSame('active', $tenant->status);

        Livewire::test(ListTenants::class)
            ->callTableAction('suspend', $tenant, data: ['reason' => 'nieoplacone faktury'])
            ->assertHasNoTableActionErrors();

        $tenant->refresh();
        $this->assertSame('suspended', $tenant->status);
        $this->assertNotNull($tenant->suspended_at);

        Livewire::test(ListTenants::class)
            ->callTableAction('reactivate', $tenant)
            ->assertHasNoTableActionErrors();

        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->suspended_at);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'master@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    private function makeTenant(): Tenant
    {
        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }
}
