<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test dla OwnerPanelProvider — sprawdza że panel jest zarejestrowany
 * i routes są mounted pod prefixem /owner.
 *
 * Pełne testy auth/dashboard/access przyjdą w PR 3 (registration flow).
 */
class OwnerPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_panel_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($routes->contains('filament.owner.pages.dashboard'),
            'Dashboard musi być mounted pod owner panel');
        $this->assertTrue($routes->contains('filament.owner.auth.login'),
            'Login musi być dostępny pod /owner/login');
    }

    public function test_owner_panel_uses_owner_path(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'filament.owner.pages.dashboard');

        $this->assertSame('owner', $route?->uri(),
            'OwnerPanelProvider musi używać path("owner")');
    }

    public function test_login_redirect_unauthenticated_user_to_login(): void
    {
        $response = $this->get('/owner');

        $response->assertRedirect('/owner/login');
    }
}
