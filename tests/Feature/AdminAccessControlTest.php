<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_get_redirected_from_admin(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect();
    }

    public function test_non_admin_user_cannot_reach_admin(): void
    {
        $user = User::create([
            'email' => 'user@example.com',
            'name' => 'Regular',
            'password' => bcrypt('secret123'),
            'is_master_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_master_admin_can_reach_admin_when_2fa_disabled(): void
    {
        config()->set('hovera.admin.require_2fa', false);

        $admin = User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_master_admin_without_2fa_is_redirected_to_setup_when_required(): void
    {
        config()->set('hovera.admin.require_2fa', true);

        $admin = User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('two-factor.setup'));
    }
}
