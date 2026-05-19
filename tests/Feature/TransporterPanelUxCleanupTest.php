<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Filament\Transport\Pages\Calculator;
use App\Http\Middleware\RedirectIfTrialExpired;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Providers\Filament\TransportPanelProvider;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Smoke checks dla "Min cleanup z auditu" (user report 2026-05-19) —
 * transporter panel UX fixes:
 *
 *   1. Calculator widoczny w nav (route + Filament page list)
 *   2. brandName tenant-aware (closure z TenantManager fallback)
 *   3. RedirectIfTrialExpired bypass dla transporterów (brak loop'a do
 *      `/app/billing` które nie istnieje w panelu /transport)
 */
class TransporterPanelUxCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_route_is_registered_in_transport_panel(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue(
            $routes->contains('filament.transport.pages.calculator'),
            'Calculator page route should be discoverable in the transport panel.'
        );
    }

    public function test_calculator_is_in_explicit_pages_list_of_transport_panel(): void
    {
        // Defensive registration — `discoverPages` plus `pages([...])` razem
        // gwarantują że Calculator nie zniknie z sidebar'a przy refactorze
        // discovery'ego.
        $provider = new TransportPanelProvider(app());
        $panel = Filament::getPanel('transport');

        $registeredPages = collect($panel->getPages())
            ->map(fn ($p) => is_string($p) ? $p : $p::class)
            ->values()
            ->all();

        $this->assertContains(Calculator::class, $registeredPages);
        unset($provider);
    }

    public function test_transporter_with_expired_trial_not_redirected_to_billing(): void
    {
        [$user, $tenant] = $this->makeOwner(TenantType::Transporter);
        $tenant->forceFill(['trial_ends_at' => Carbon::now()->subDays(5)])->save();

        $this->actingAs($user);
        app(TenantManager::class)->setCurrent($tenant);

        $request = Request::create('/transport/quotes', 'GET');
        $middleware = app(RedirectIfTrialExpired::class);

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_stable_with_expired_trial_still_redirected_to_billing(): void
    {
        // Regresja — bypass jest tylko dla Transporter, Stable flow musi
        // nadal redirectować do `/app/billing`.
        [$user, $tenant] = $this->makeOwner(TenantType::Stable);
        $tenant->forceFill(['trial_ends_at' => Carbon::now()->subDays(5)])->save();

        $this->actingAs($user);
        app(TenantManager::class)->setCurrent($tenant);

        $request = Request::create('/app/horses', 'GET');
        $middleware = app(RedirectIfTrialExpired::class);

        $response = $middleware->handle($request, fn () => response('should-not-reach', 200));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('billing', (string) $response->headers->get('Location'));
    }

    public function test_master_admin_bypass_unchanged(): void
    {
        // Regresja — master admin nadal bypass'uje wszystkie gates.
        $admin = User::create([
            'email' => 'master-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => bcrypt('secret'),
            'is_master_admin' => true,
        ]);
        Auth::login($admin);

        $request = Request::create('/app/horses', 'GET');
        $middleware = app(RedirectIfTrialExpired::class);

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
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
            'slug' => 't-'.uniqid(),
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
