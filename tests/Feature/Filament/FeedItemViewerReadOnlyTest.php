<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\TenantType;
use App\Filament\App\Resources\FeedItemResource;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\FeedItem;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Po audycie dostepu vet/employee — sprawdzamy ze viewer JEST w
 * FEED_STAFF (moze ogladac stan paszy w raportach), ale NIE moze
 * CRUD'owac. CanCreate/canEdit/canDelete override'uja parent.
 *
 * Employee i manager — moga CRUD (operacje codzienne stajenne).
 */
class FeedItemViewerReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_create_feed_item(): void
    {
        $this->bootTenantWithRole('viewer');
        $this->assertFalse(FeedItemResource::canCreate());
    }

    public function test_viewer_cannot_edit_feed_item(): void
    {
        $this->bootTenantWithRole('viewer');
        $this->assertFalse(FeedItemResource::canEdit(new FeedItem));
    }

    public function test_viewer_cannot_delete_feed_item(): void
    {
        $this->bootTenantWithRole('viewer');
        $this->assertFalse(FeedItemResource::canDelete(new FeedItem));
    }

    public function test_employee_can_create_feed_item(): void
    {
        // Stajenny — codzienne wydania paszy, oczywista operacja.
        $this->bootTenantWithRole('employee');
        $this->assertTrue(FeedItemResource::canCreate());
    }

    public function test_manager_can_full_crud(): void
    {
        $this->bootTenantWithRole('manager');
        $this->assertTrue(FeedItemResource::canCreate());
        $this->assertTrue(FeedItemResource::canEdit(new FeedItem));
        $this->assertTrue(FeedItemResource::canDelete(new FeedItem));
    }

    private function bootTenantWithRole(string $role): void
    {
        $tenant = Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Stajnia',
            'type' => TenantType::Stable,
            'db_name' => 'stajnia_'.uniqid(),
            'db_username' => 'stajnia_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        $user = User::create([
            'email' => $role.'-'.uniqid().'@example.com',
            'name' => ucfirst($role),
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('tenantOrFail')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturn(true);
        });
        Auth::login($user);
    }
}
