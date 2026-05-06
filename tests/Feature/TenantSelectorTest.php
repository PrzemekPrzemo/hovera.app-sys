<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_open_selector(): void
    {
        $this->get('/tenant/select')->assertRedirect();
    }

    public function test_user_without_memberships_sees_dead_end_page(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/tenant/select')
            ->assertOk()
            ->assertSeeText('Brak dostępnych stajni');
    }

    public function test_user_with_one_membership_is_auto_redirected(): void
    {
        $user = $this->makeUser();
        $tenant = $this->makeTenant('acme');
        $this->makeMembership($user, $tenant);

        $response = $this->actingAs($user)->get('/tenant/select');
        $response->assertRedirect('/app');

        $this->assertSame($tenant->id, session('current_tenant_id'));
    }

    public function test_user_with_multiple_memberships_sees_chooser(): void
    {
        $user = $this->makeUser();
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');
        $this->makeMembership($user, $a);
        $this->makeMembership($user, $b);

        $this->actingAs($user)
            ->get('/tenant/select')
            ->assertOk()
            ->assertSeeText('Wybierz stajnię')
            ->assertSeeText('alpha')
            ->assertSeeText('beta');

        $this->assertNull(session('current_tenant_id'));
    }

    public function test_choose_sets_current_tenant_and_redirects_to_app(): void
    {
        $user = $this->makeUser();
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');
        $this->makeMembership($user, $a);
        $this->makeMembership($user, $b);

        $response = $this->actingAs($user)->post('/tenant/select', [
            'tenant_id' => $b->id,
        ]);

        $response->assertRedirect('/app');
        $this->assertSame($b->id, session('current_tenant_id'));
    }

    public function test_choose_rejects_tenant_user_does_not_belong_to(): void
    {
        $user = $this->makeUser();
        $a = $this->makeTenant('alpha');
        $b = $this->makeTenant('beta');
        $this->makeMembership($user, $a);
        // No membership in B!

        $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $b->id])
            ->assertRedirect();

        $this->assertNull(session('current_tenant_id'));
    }

    public function test_choose_rejects_suspended_tenant(): void
    {
        $user = $this->makeUser();
        $tenant = $this->makeTenant('paused', 'suspended');
        $this->makeMembership($user, $tenant);

        $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $tenant->id])
            ->assertRedirect();

        $this->assertNull(session('current_tenant_id'));
    }

    public function test_switch_clears_session_and_redirects(): void
    {
        $user = $this->makeUser();
        $tenant = $this->makeTenant('acme');
        $this->makeMembership($user, $tenant);

        session(['current_tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->get('/tenant/switch')
            ->assertRedirect(route('tenant.select'));

        $this->assertNull(session('current_tenant_id'));
    }

    private function makeUser(): User
    {
        return User::create([
            'email' => 'jeździec@example.com',
            'name' => 'Jeździec',
            'password' => bcrypt('secret123'),
        ]);
    }

    private function makeTenant(string $slug, string $status = 'active'): Tenant
    {
        $tenant = new Tenant([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'db_name' => 'hovera_t_'.$slug,
            'db_username' => 'hovera_t_'.$slug,
            'status' => $status,
        ]);
        $tenant->db_password = 'irrelevant_for_tests';
        $tenant->save();

        return $tenant;
    }

    private function makeMembership(User $user, Tenant $tenant, string $role = 'owner'): TenantMembership
    {
        return TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }
}
