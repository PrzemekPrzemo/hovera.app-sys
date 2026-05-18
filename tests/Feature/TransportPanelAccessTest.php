<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/transport');

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location') ?? '');
    }

    public function test_stable_tenant_owner_redirected_to_app_panel(): void
    {
        [$user, $tenant] = $this->makeOwner(TenantType::Stable);

        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/transport')
            ->assertRedirect('/app');
    }

    public function test_route_for_transport_dashboard_is_registered(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($routes->contains('filament.transport.pages.dashboard'));
        $this->assertTrue($routes->contains('filament.transport.auth.login'));
    }

    private function makeOwner(TenantType $type): array
    {
        $user = User::create([
            'email' => 'owner-'.uniqid().'@example.com',
            'name' => 'Owner',
            'password' => bcrypt('secret123'),
        ]);

        $tenant = new Tenant([
            'slug' => 'acme-'.uniqid(),
            'name' => 'Acme',
            'type' => $type,
            'db_name' => 'hovera_t_'.uniqid(),
            'db_username' => 'hovera_t_'.uniqid(),
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$user, $tenant];
    }
}
