<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Memberships\AttachOrInviteUser;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttachOrInviteUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_attaches_existing_user_without_creating_new_account(): void
    {
        $tenant = $this->makeTenant('alpha');
        $existing = User::create([
            'email' => 'jeździec@example.com',
            'name' => 'Jeździec',
            'password' => Hash::make('original'),
        ]);

        $result = $this->action()->execute([
            'tenant_id' => $tenant->id,
            'email' => 'JEŹDZIEC@example.com',   // case-insensitive
            'role' => 'instructor',
        ]);

        $this->assertSame($existing->id, $result['user']->id);
        $this->assertNull($result['generated_password']);
        $this->assertSame('instructor', $result['membership']->role);
        $this->assertNotNull($result['membership']->joined_at);
        $this->assertNull($result['membership']->revoked_at);

        // Existing password unchanged
        $this->assertTrue(Hash::check('original', $existing->refresh()->password));
    }

    public function test_creates_new_user_when_email_unknown_and_returns_password(): void
    {
        $tenant = $this->makeTenant('beta');

        $result = $this->action()->execute([
            'tenant_id' => $tenant->id,
            'email' => 'nowy@example.com',
            'name' => 'Nowy User',
            'role' => 'manager',
        ]);

        $this->assertNotNull($result['generated_password']);
        $this->assertSame(20, strlen($result['generated_password']));
        $this->assertSame('Nowy User', $result['user']->name);
        $this->assertTrue(Hash::check($result['generated_password'], $result['user']->password));
        $this->assertSame('manager', $result['membership']->role);
    }

    public function test_reactivates_a_revoked_membership(): void
    {
        $tenant = $this->makeTenant('gamma');
        $user = User::create([
            'email' => 'old@example.com',
            'name' => 'Old',
            'password' => Hash::make('whatever'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'joined_at' => now()->subMonth(),
            'revoked_at' => now()->subDay(),
        ]);

        $result = $this->action()->execute([
            'tenant_id' => $tenant->id,
            'email' => 'old@example.com',
            'role' => 'admin',
        ]);

        $this->assertNull($result['membership']->revoked_at);
        $this->assertSame('admin', $result['membership']->role);
        $this->assertSame(1, TenantMembership::where('user_id', $user->id)->count());
    }

    public function test_rejects_invalid_role(): void
    {
        $tenant = $this->makeTenant('delta');
        $this->expectException(ValidationException::class);

        $this->action()->execute([
            'tenant_id' => $tenant->id,
            'email' => 'someone@example.com',
            'role' => 'overlord',
        ]);
    }

    public function test_rejects_unknown_tenant(): void
    {
        $this->expectException(ValidationException::class);

        $this->action()->execute([
            'tenant_id' => '01HXXXX0000000000000000000',
            'email' => 'someone@example.com',
            'role' => 'viewer',
        ]);
    }

    private function action(): AttachOrInviteUser
    {
        return $this->app->make(AttachOrInviteUser::class);
    }

    private function makeTenant(string $slug): Tenant
    {
        $t = new Tenant([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'db_name' => 'hovera_t_'.$slug,
            'db_username' => 'hovera_t_'.$slug,
            'status' => 'active',
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        return $t;
    }
}
