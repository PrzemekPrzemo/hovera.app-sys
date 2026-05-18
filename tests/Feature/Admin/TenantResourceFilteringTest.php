<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\TenantResource;
use App\Filament\Admin\Resources\TenantResource\Pages\ListTenants;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TenantResource teraz zawiera wszystkich tenantów (stable + transporter)
 * + filtr `type` żeby master admin mógł zawęzić widok.
 */
class TenantResourceFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_table_lists_both_stable_and_transporter_tenants(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makeTenant(TenantType::Stable);
        $tr = $this->makeTenant(TenantType::Transporter);

        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords([$stable, $tr]);
    }

    public function test_type_filter_returns_only_stables(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makeTenant(TenantType::Stable);
        $tr = $this->makeTenant(TenantType::Transporter);

        Livewire::test(ListTenants::class)
            ->filterTable('type', TenantType::Stable->value)
            ->assertCanSeeTableRecords([$stable])
            ->assertCanNotSeeTableRecords([$tr]);
    }

    public function test_type_filter_returns_only_transporters(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makeTenant(TenantType::Stable);
        $tr = $this->makeTenant(TenantType::Transporter);

        Livewire::test(ListTenants::class)
            ->filterTable('type', TenantType::Transporter->value)
            ->assertCanSeeTableRecords([$tr])
            ->assertCanNotSeeTableRecords([$stable]);
    }

    public function test_navigation_label_uses_all_tenants(): void
    {
        // Aby nie regresować: master admin musi widzieć etykietę "Wszyscy tenanci"
        $this->assertSame(__('navigation.all_tenants'), TenantResource::getNavigationLabel());
        // Smoke: klucz jest zlokalizowany (nie zwraca dosłownie nazwy klucza).
        $this->assertNotSame('navigation.all_tenants', __('navigation.all_tenants'));
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'master-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    private function makeTenant(TenantType $type): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 't-'.$u,
            'name' => 'Test '.$u,
            'type' => $type,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
