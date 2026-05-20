<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TenantTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'slug' => 'acme-'.uniqid(),
            'name' => 'Acme',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'hovera_t_'.uniqid(),
            'db_username' => 'hovera_t_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('secret'),
        ], $overrides));
    }

    public function test_new_tenant_defaults_to_stable(): void
    {
        $tenant = $this->makeTenant();

        $this->assertSame(TenantType::Stable, $tenant->fresh()->type);
        $this->assertTrue($tenant->fresh()->isStable());
        $this->assertFalse($tenant->fresh()->isTransporter());
    }

    public function test_transporter_tenant_can_be_created_explicitly(): void
    {
        $tenant = $this->makeTenant(['type' => TenantType::Transporter]);

        $this->assertSame(TenantType::Transporter, $tenant->fresh()->type);
        $this->assertTrue($tenant->fresh()->isTransporter());
        $this->assertFalse($tenant->fresh()->isStable());
    }

    public function test_scopes_split_tenants_by_type(): void
    {
        $this->makeTenant(['slug' => 'stable-a']);
        $this->makeTenant(['slug' => 'stable-b']);
        $this->makeTenant(['slug' => 'transporter-a', 'type' => TenantType::Transporter]);
        $this->makeTenant(['slug' => 'owner-a', 'type' => TenantType::HorseOwner]);
        $this->makeTenant(['slug' => 'owner-b', 'type' => TenantType::HorseOwner]);

        $this->assertSame(2, Tenant::stables()->count());
        $this->assertSame(1, Tenant::transporters()->count());
        $this->assertSame(2, Tenant::horseOwners()->count());
    }

    public function test_horse_owner_tenant_predicates(): void
    {
        $tenant = $this->makeTenant(['type' => TenantType::HorseOwner])->fresh();

        $this->assertTrue($tenant->isHorseOwner());
        $this->assertFalse($tenant->isStable());
        $this->assertFalse($tenant->isTransporter());
    }

    public function test_enum_panel_id_mapping(): void
    {
        $this->assertSame('app', TenantType::Stable->panelId());
        $this->assertSame('transport', TenantType::Transporter->panelId());
        $this->assertSame('owner', TenantType::HorseOwner->panelId());
    }

    public function test_horse_owner_is_free_tier_others_are_not(): void
    {
        $this->assertTrue(TenantType::HorseOwner->isFreeTier());
        $this->assertFalse(TenantType::Stable->isFreeTier());
        $this->assertFalse(TenantType::Transporter->isFreeTier());
    }
}
