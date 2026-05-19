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
 * Po PR #241 (publiczny landing) URL `/transport` przeszedł na
 * `TransportLandingController` — root jest publiczny, panel Filament
 * żyje pod sub-route'ami (`/transport/leads`, `/transport/calculator`, etc.).
 *
 * Auth-aware landing zachowuje się tak:
 *   - guest → 200 (publiczny landing z hero + Top 10 + formularz)
 *   - auth stable owner → 302 do `/transport/zapytanie?stable={id}`
 *   - auth transporter owner → 302 do `/transport/quotes` (panel home)
 *
 * Patrz `TransportLandingController::redirectForAuthenticated()`.
 */
class TransportPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_lands_on_public_transport_page(): void
    {
        // Po #241 landing jest publiczny — zwraca 200 z hero/Top 10/formularzem
        // zamiast redirectu na login. Login dla panel Filament żyje pod
        // `/transport/login` osobno.
        $this->get('/transport')->assertOk();
    }

    public function test_filament_login_redirects_unauthenticated_panel_access(): void
    {
        // Próba dostępu do sub-route'a panel (np. /transport/quotes) bez auth
        // → Filament redirectuje na login.
        $response = $this->get('/transport/quotes');

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location') ?? '');
    }

    public function test_stable_tenant_owner_redirected_to_inquiry_with_prefill(): void
    {
        // Po #241 landing dla stable owner'a redirectuje do publicznego
        // formularza zapytania z pre-fill stable_id (klient stable może chcieć
        // zamówić transport dla swojego konia).
        [$user, $tenant] = $this->makeOwner(TenantType::Stable);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/transport');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/transport/zapytanie', $location);
        $this->assertStringContainsString('stable='.$tenant->id, $location);
    }

    public function test_calculator_and_login_routes_are_registered(): void
    {
        // Dashboard route name (`filament.transport.pages.dashboard`) nie
        // istnieje już po #241 bo TransportLandingController przejął root
        // `/transport`. Sprawdzamy zamiast tego dwie inne route'y żeby
        // potwierdzić że panel Filament jest skonfigurowany:
        //   - `filament.transport.pages.calculator` (key page, #244 dodał do
        //     explicit pages[])
        //   - `filament.transport.auth.login` (Filament login flow działa)
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue(
            $routes->contains('filament.transport.pages.calculator'),
            'Calculator route should be registered (added explicitly in #244).'
        );
        $this->assertTrue(
            $routes->contains('filament.transport.auth.login'),
            'Filament login route should be registered.'
        );
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
