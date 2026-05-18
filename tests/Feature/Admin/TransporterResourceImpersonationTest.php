<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Filament\Admin\Resources\TransporterResource;
use App\Filament\Admin\Resources\TransporterResource\Pages\ListTransporters;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Akcja `login_as_owner` na TransporterResource — master admin może impersonować
 * ownera firmy transportowej bez wchodzenia w globalny TenantResource.
 */
class TransporterResourceImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->mock(\App\Services\MasterAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    /**
     * Wstrzykuje session store na ten Request, którego Livewire używa w wywołaniu
     * akcji (sam Livewire tworzy nowy Request, więc trzeba zrobić to po jego
     * inicjalizacji w teście).
     */
    private function ensureSession(): void
    {
        if (! request()->hasSession()) {
            request()->setLaravelSession($this->app->make('session.store'));
        }
    }

    public function test_login_as_owner_action_is_registered_and_visible_for_active_transporter(): void
    {
        $this->actingAsMasterAdmin();
        [$tenant, $owner] = $this->makeTransporterWithOwner();

        Livewire::test(ListTransporters::class)
            ->assertTableActionExists('login_as_owner')
            ->assertTableActionVisible('login_as_owner', $tenant);
    }

    public function test_login_as_owner_is_hidden_for_transporter_without_active_user(): void
    {
        $this->actingAsMasterAdmin();
        // Transporter bez membership — akcja musi być ukryta (admin nie ma kogo
        // impersonować i komunikat błędu byłby mylący).
        $u = uniqid();
        $tenant = Tenant::create([
            'slug' => 'tr-empty-'.$u,
            'name' => 'Empty Transporter '.$u,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Pending,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        Livewire::test(ListTransporters::class)
            ->assertTableActionHidden('login_as_owner', $tenant);
    }

    public function test_login_as_owner_requires_reason(): void
    {
        $this->actingAsMasterAdmin();
        [$tenant] = $this->makeTransporterWithOwner();

        Livewire::test(ListTransporters::class)
            ->callTableAction('login_as_owner', $tenant, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason']);
    }

    public function test_resource_exposes_membership_and_invitation_relations(): void
    {
        // Relacje są kluczowe: master admin widzi userów i invitations
        // bezpośrednio z poziomu listy transporterów.
        $relations = TransporterResource::getRelations();

        $this->assertContains(
            \App\Filament\Admin\Resources\TenantResource\RelationManagers\MembershipsRelationManager::class,
            $relations,
        );
        $this->assertContains(
            \App\Filament\Admin\Resources\TenantResource\RelationManagers\InvitationsRelationManager::class,
            $relations,
        );
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'tr-admin-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    /** @return array{0: Tenant, 1: User} */
    private function makeTransporterWithOwner(): array
    {
        $u = uniqid();
        $tenant = Tenant::create([
            'slug' => 'tr-'.$u,
            'name' => 'Transporter '.$u,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $owner = User::create([
            'email' => 'owner-'.$u.'@example.com',
            'name' => 'Owner',
            'password' => Hash::make('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$tenant, $owner];
    }
}
