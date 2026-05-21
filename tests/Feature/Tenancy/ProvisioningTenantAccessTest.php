<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresja: transporter w statusie `provisioning` (świeży signup
 * lub świeża impersonacja po PR #357 master admin login-as) wpadał w
 * ERR_TOO_MANY_REDIRECTS:
 *   /transport/dashboard → InitialiseTenantFromSession → !isUsable
 *   → /tenant/select → SELECTABLE includes provisioning → set tenant
 *   → /transport → redirect /transport/dashboard → ...
 *
 * Fix: Tenant::PANEL_ACCESSIBLE_STATUSES = ['provisioning', 'trialing',
 * 'active', 'past_due']. Single source of truth — używana zarówno przez
 * `isUsable()` (InitialiseTenantFromSession) jak `SELECTABLE_TENANT_
 * STATUSES` (TenantSelectorController). Bez tego mismatch'u — bez loop'a.
 */
class ProvisioningTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_transporter_is_usable(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter, 'provisioning');

        $this->assertTrue($tenant->isUsable(), 'provisioning transporter MUSI być isUsable żeby uniknąć /transport/dashboard redirect loop');
    }

    public function test_provisioning_in_panel_accessible_statuses_constant(): void
    {
        $this->assertContains('provisioning', Tenant::PANEL_ACCESSIBLE_STATUSES);
        $this->assertContains('trialing', Tenant::PANEL_ACCESSIBLE_STATUSES);
        $this->assertContains('active', Tenant::PANEL_ACCESSIBLE_STATUSES);
        $this->assertContains('past_due', Tenant::PANEL_ACCESSIBLE_STATUSES);
    }

    public function test_suspended_tenant_not_usable(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter, 'suspended');
        $this->assertFalse($tenant->isUsable());
    }

    public function test_active_tenant_usable(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable, 'active');
        $this->assertTrue($tenant->isUsable());
    }

    private function makeTenant(TenantType $type, string $status): Tenant
    {
        return Tenant::create([
            'slug' => 'pa-'.Str::random(6),
            'name' => 'Test '.$status,
            'type' => $type,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
        ]);
    }
}
