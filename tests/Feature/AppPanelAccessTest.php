<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `RequireTenantType:stable` middleware na `/app` (AppPanelProvider) bouncuje
 * non-stable tenantów. Symetryczne do `RequireTenantType:transporter` na
 * `/transport`. Patrz docs/TRANSPORT.md §3.1 + PR-A z planu fix-panels.
 *
 * Login flow Filamenta: `/app/login` jest jedynym endpoint'em logowania
 * (Transport panel ma własny `/transport/login` ale w praktyce wszyscy
 * lądują na /app/login). Bez tego middleware'a transporter który zaloguje
 * się na /app/login lądował na /app i widział stable navigation
 * (konie / zabiegi / kalendarz) zamiast /transport (kalkulator, leady).
 */
class AppPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_transporter_owner_visiting_app_panel_redirected_to_transport(): void
    {
        [$user, $tenant] = $this->makeOwner(TenantType::Transporter);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/app');

        $response->assertRedirect('/transport');
    }

    public function test_stable_owner_can_access_app_panel(): void
    {
        // Regresja: stable owner ląduje na /app bez bounce'a.
        [$user, $tenant] = $this->makeOwner(TenantType::Stable);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/app');

        // Nie powinien być redirect na /transport. Może być inne (np. tenant
        // suspended / trial expired) ale na pewno nie panel-mismatch.
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/transport', $location);
    }

    public function test_master_admin_bypasses_tenant_type_gate(): void
    {
        // Master admin z `current_tenant_id` transportera może wejść na /app
        // (debug use case: master admin chce zobaczyć panel jak właściciel
        // stable albo by zaobserwować transporter'a w stable widoku).
        // RequireTenantType lines 42-45 robi explicit bypass dla master admina.
        $master = User::create([
            'email' => 'master-'.uniqid().'@example.com',
            'name' => 'Master',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);

        [, $transporterTenant] = $this->makeOwner(TenantType::Transporter);

        $response = $this->actingAs($master)
            ->withSession(['current_tenant_id' => $transporterTenant->id])
            ->get('/app');

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/transport', $location);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
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
