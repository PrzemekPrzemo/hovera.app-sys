<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\App\Pages\TenantSettings;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class TenantSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Stub the audit logger — these tests don't exercise per-tenant
        // DB writes, only the Filament page logic.
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    public function test_owner_can_access_and_save(): void
    {
        [$user, $tenant] = $this->scenario('owner');
        $this->bootContext($user, $tenant);

        Livewire::test(TenantSettings::class)
            ->set('data.name', 'Stajnia Nowa Nazwa')
            ->set('data.legal_name', 'Acme Sp. z o.o.')
            ->set('data.tax_id', '5252345678')
            ->set('data.country', 'PL')
            ->set('data.locale', 'pl')
            ->set('data.timezone', 'Europe/Warsaw')
            ->set('data.currency', 'PLN')
            ->set('data.primary_color', '#10b981')
            ->set('data.logo_url', 'https://example.com/logo.png')
            ->call('save')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame('Stajnia Nowa Nazwa', $tenant->name);
        $this->assertSame('Acme Sp. z o.o.', $tenant->legal_name);
        $this->assertSame('5252345678', $tenant->tax_id);
        $this->assertSame('#10b981', $tenant->branding['primary_color']);
        $this->assertSame('https://example.com/logo.png', $tenant->branding['logo_url']);
    }

    public function test_admin_role_can_access(): void
    {
        [$user, $tenant] = $this->scenario('admin');
        $this->bootContext($user, $tenant);

        Livewire::test(TenantSettings::class)
            ->assertOk();
    }

    public function test_instructor_cannot_access(): void
    {
        [$user, $tenant] = $this->scenario('instructor');
        $this->bootContext($user, $tenant);

        Livewire::test(TenantSettings::class)->assertForbidden();
    }

    public function test_revoked_membership_cannot_access(): void
    {
        [$user, $tenant, $membership] = $this->scenario('owner');
        $membership->forceFill(['revoked_at' => now()])->save();
        $this->bootContext($user, $tenant);

        Livewire::test(TenantSettings::class)->assertForbidden();
    }

    /**
     * @return array{0:User,1:Tenant,2:TenantMembership}
     */
    private function scenario(string $role): array
    {
        $user = User::create([
            'email' => "{$role}@example.com",
            'name' => ucfirst($role),
            'password' => Hash::make('secret'),
        ]);

        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        return [$user, $tenant, $membership];
    }

    private function bootContext(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);
        $this->app['session']->driver()->put('current_tenant_id', $tenant->id);

        // Mark tenant active on the manager without reconfiguring the
        // connection (audit logging will no-op because no connection
        // is wired, which is fine for these tests).
        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }
}
