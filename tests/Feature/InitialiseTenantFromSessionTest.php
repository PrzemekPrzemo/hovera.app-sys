<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\InitialiseTenantFromSession;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InitialiseTenantFromSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_login_when_unauthenticated(): void
    {
        $middleware = $this->app->make(InitialiseTenantFromSession::class);
        $request = Request::create('/app');
        $request->setLaravelSession($this->app['session']->driver());

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_redirects_to_selector_when_no_tenant_in_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $middleware = $this->app->make(InitialiseTenantFromSession::class);
        $request = Request::create('/app');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/tenant/select', $response->getTargetUrl());
    }

    public function test_redirects_to_selector_when_membership_was_revoked(): void
    {
        $user = $this->makeUser();
        $tenant = $this->makeTenant('acme');
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
            'revoked_at' => now(),    // ← revoked!
        ]);

        $this->actingAs($user);
        $session = $this->app['session']->driver();
        $session->put('current_tenant_id', $tenant->id);

        $middleware = $this->app->make(InitialiseTenantFromSession::class);
        $request = Request::create('/app');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($session->get('current_tenant_id'));
    }

    public function test_passes_through_and_sets_tenant_when_membership_active(): void
    {
        $user = $this->makeUser();
        $tenant = $this->makeTenant('acme');
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($user);
        $session = $this->app['session']->driver();
        $session->put('current_tenant_id', $tenant->id);

        $middleware = $this->app->make(InitialiseTenantFromSession::class);
        $request = Request::create('/app');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame('next', $response->getContent());
        $this->assertTrue($this->app->make(TenantManager::class)->hasTenant());
        $this->assertSame($tenant->id, $this->app->make(TenantManager::class)->current()?->id);
    }

    private function makeUser(): User
    {
        return User::create([
            'email' => 'foo@example.com',
            'name' => 'Foo',
            'password' => bcrypt('secret123'),
        ]);
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
