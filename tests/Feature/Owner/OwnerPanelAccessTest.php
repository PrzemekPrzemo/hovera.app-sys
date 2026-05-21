<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresja: `/owner` panel wcześniej dawał 403 zaraz po impersonacji
 * z master admin (`/admin/horse-owners` → "Zaloguj jako właściciel"),
 * bo `User::canAccessPanel()` miał match TYLKO dla admin/app/transport,
 * `owner` wpadał w default => false.
 */
class OwnerPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_horse_owner_user_with_membership_can_access_owner_panel(): void
    {
        $owner = $this->makeTenant(TenantType::HorseOwner);
        $user = User::create([
            'name' => 'Jan',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $owner->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $ownerPanel = Filament::getPanel('owner');

        $this->assertTrue(
            $user->canAccessPanel($ownerPanel),
            'Horse owner z active membership musi mieć dostęp do /owner panel',
        );
    }

    public function test_master_admin_can_access_owner_panel(): void
    {
        $admin = User::create([
            'name' => 'Master',
            'email' => 'master-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
            'is_master_admin' => true,
        ]);

        $ownerPanel = Filament::getPanel('owner');

        $this->assertTrue(
            $admin->canAccessPanel($ownerPanel),
            'Master admin musi mieć dostęp do /owner panel (impersonacja).',
        );
    }

    public function test_user_without_memberships_cannot_access_owner_panel(): void
    {
        $stranger = User::create([
            'name' => 'Stranger',
            'email' => 'stranger-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $ownerPanel = Filament::getPanel('owner');

        $this->assertFalse(
            $stranger->canAccessPanel($ownerPanel),
            'User bez żadnego membership NIE może wchodzić na /owner (anti-bypass).',
        );
    }

    private function makeTenant(TenantType $type): Tenant
    {
        $tenant = new Tenant([
            'slug' => strtolower(str_replace('_', '-', $type->value)).'-'.uniqid(),
            'name' => 'Test '.$type->value,
            'type' => $type,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        $tenant->save();

        return $tenant;
    }
}
