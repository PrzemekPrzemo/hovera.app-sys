<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\InvitationResource\Pages\ListInvitations;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Central\UserInvitation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master admin musi móc filtrować zaproszenia po typie tenanta (stable vs transporter)
 * — żeby szybko zobaczyć wszystkie pending invitations dla transporterów.
 */
class InvitationResourceFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_tenant_type_filter_isolates_stable_invitations(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makeTenant(TenantType::Stable);
        $transporter = $this->makeTenant(TenantType::Transporter);

        $stableInv = $this->makeInvitation($stable);
        $trInv = $this->makeInvitation($transporter);

        Livewire::test(ListInvitations::class)
            ->filterTable('tenant_type', TenantType::Stable->value)
            ->assertCanSeeTableRecords([$stableInv])
            ->assertCanNotSeeTableRecords([$trInv]);
    }

    public function test_tenant_type_filter_isolates_transporter_invitations(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makeTenant(TenantType::Stable);
        $transporter = $this->makeTenant(TenantType::Transporter);

        $stableInv = $this->makeInvitation($stable);
        $trInv = $this->makeInvitation($transporter);

        Livewire::test(ListInvitations::class)
            ->filterTable('tenant_type', TenantType::Transporter->value)
            ->assertCanSeeTableRecords([$trInv])
            ->assertCanNotSeeTableRecords([$stableInv]);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'inv-admin-'.uniqid().'@hovera.app',
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

    private function makeInvitation(Tenant $tenant): UserInvitation
    {
        return UserInvitation::create([
            'tenant_id' => $tenant->id,
            'email' => 'inv-'.uniqid().'@example.com',
            'role' => 'viewer',
            'token_hash' => hash('sha256', 'tok-'.uniqid()),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
