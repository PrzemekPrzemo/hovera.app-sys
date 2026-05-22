<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\TenantType;
use App\Http\Middleware\RedirectToOnboarding;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Faza D — onboarding wizard per typ tenanta.
 *
 * Pokrywa:
 *  - Tenant helpers `isOnboardingFinished` + `markOnboardingFinished`
 *  - Idempotency (markOnboardingFinished nie nadpisuje istniejacego timestamp)
 *  - Middleware redirect logic dla 3 typow tenanta + master admin bypass
 *  - Anti-loop (gdy juz na wizard URL → przepuszcza)
 */
class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_onboarding_finished_false_by_default(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable);
        $this->assertFalse($tenant->isOnboardingFinished());
    }

    public function test_mark_onboarding_finished_completed_sets_timestamp(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable);
        $tenant->markOnboardingFinished('completed');

        $tenant->refresh();
        $this->assertTrue($tenant->isOnboardingFinished());
        $this->assertNotEmpty(data_get($tenant->settings, 'onboarding.completed_at'));
        $this->assertEmpty(data_get($tenant->settings, 'onboarding.skipped_at'));
    }

    public function test_mark_onboarding_finished_skipped_sets_timestamp(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter);
        $tenant->markOnboardingFinished('skipped');

        $tenant->refresh();
        $this->assertTrue($tenant->isOnboardingFinished());
        $this->assertNotEmpty(data_get($tenant->settings, 'onboarding.skipped_at'));
        $this->assertEmpty(data_get($tenant->settings, 'onboarding.completed_at'));
    }

    public function test_mark_onboarding_finished_is_idempotent(): void
    {
        $tenant = $this->makeTenant(TenantType::HorseOwner);
        $tenant->markOnboardingFinished('completed');
        $tenant->refresh();
        $first = (string) data_get($tenant->settings, 'onboarding.completed_at');

        // Drugi call NIE zmienia timestamp'u — single source of truth dla
        // „first time" / „kiedy user faktycznie ukonczyl wizard".
        $tenant->markOnboardingFinished('completed');
        $tenant->refresh();
        $second = (string) data_get($tenant->settings, 'onboarding.completed_at');

        $this->assertSame($first, $second);
    }

    public function test_mark_onboarding_finished_deferred_sets_deferred_at(): void
    {
        $tenant = $this->makeTenant(TenantType::HorseOwner);
        $tenant->markOnboardingFinished('deferred');
        $tenant->refresh();

        // Silent deferral — wizard byl pokazany, ale user nie kliknal
        // ani Finish ani Skip. Middleware go nie zatrzymuje, ale banner
        // wciaz pokazuje "dokoncz onboarding".
        $this->assertFalse($tenant->isOnboardingFinished());
        $this->assertTrue($tenant->wasOnboardingShown());
        $this->assertNotEmpty(data_get($tenant->settings, 'onboarding.deferred_at'));
    }

    public function test_completed_and_skipped_also_count_as_shown(): void
    {
        $a = $this->makeTenant(TenantType::Stable);
        $a->markOnboardingFinished('completed');
        $this->assertTrue($a->fresh()->wasOnboardingShown());

        $b = $this->makeTenant(TenantType::Transporter);
        $b->markOnboardingFinished('skipped');
        $this->assertTrue($b->fresh()->wasOnboardingShown());
    }

    public function test_middleware_passes_through_when_only_deferred(): void
    {
        // User pierwszy raz wszedl na wizarda (auto-deferred w mount),
        // potem kliknal w nawigacje do innej strony. Middleware musi go
        // przepuscic — inaczej user utknie w petli "redirect na wizard".
        $tenant = $this->makeTenant(TenantType::HorseOwner);
        $tenant->markOnboardingFinished('deferred');
        $tenant->refresh();

        $response = $this->runMiddleware($tenant, '/owner', '/owner/horses');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_redirects_unfinished_stable_to_app_wizard(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable);
        $response = $this->runMiddleware($tenant, '/app', '/app/dashboard');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/app/onboarding-wizard', (string) $response->headers->get('Location'));
    }

    public function test_middleware_redirects_unfinished_transporter_to_transport_wizard(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter);
        $response = $this->runMiddleware($tenant, '/transport', '/transport/dashboard');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/transport/onboarding-wizard', (string) $response->headers->get('Location'));
    }

    public function test_middleware_redirects_unfinished_horse_owner_to_owner_wizard(): void
    {
        $tenant = $this->makeTenant(TenantType::HorseOwner);
        $response = $this->runMiddleware($tenant, '/owner', '/owner/horses');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/owner/onboarding-wizard', (string) $response->headers->get('Location'));
    }

    public function test_middleware_passes_through_when_already_on_wizard_url(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable);
        $response = $this->runMiddleware($tenant, '/app', '/app/onboarding-wizard');

        // Anti-loop — gdy user juz jest na wizard'zie, middleware
        // przepuszcza, inaczej infinite redirect.
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_passes_through_when_onboarding_finished(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable);
        $tenant->markOnboardingFinished('completed');
        $tenant->refresh();

        $response = $this->runMiddleware($tenant, '/app', '/app/dashboard');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_passes_through_when_skipped(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter);
        $tenant->markOnboardingFinished('skipped');
        $tenant->refresh();

        $response = $this->runMiddleware($tenant, '/transport', '/transport/dashboard');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_bypasses_master_admin(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable);
        $admin = User::create([
            'email' => 'master-'.uniqid().'@example.com',
            'name' => 'Master',
            'password' => bcrypt('secret'),
            'is_master_admin' => true,
        ]);

        $response = $this->runMiddleware($tenant, '/app', '/app/dashboard', $admin);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_passes_through_when_no_tenant_attached(): void
    {
        // Brak tenant'a (np. /select-tenant) → middleware musi przepuscic
        // request (inaczej redirect loop przy wyborze tenanta).
        $request = Request::create('/select-tenant', 'GET');
        $next = fn () => new Response('ok', 200);

        $response = (new RedirectToOnboarding)->handle($request, $next);
        $this->assertSame(200, $response->getStatusCode());
    }

    private function makeTenant(TenantType $type): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => $type,
            'db_name' => 'firma_'.uniqid(),
            'db_username' => 'firma_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    /**
     * Symuluje middleware z `$tenant` attachowanym do request (jak po
     * InitialiseTenantFromSession). $next zwraca 200 — czyli "request
     * przeszedl", w przeciwnym razie middleware zwraca redirect.
     */
    private function runMiddleware(Tenant $tenant, string $panelBase, string $path, ?User $user = null): Response
    {
        $request = Request::create($path, 'GET');
        $request->attributes->set('tenant', $tenant);
        if ($user !== null) {
            $request->setUserResolver(fn () => $user);
        }

        $next = fn (): Response => new Response('ok', 200);

        return (new RedirectToOnboarding)->handle($request, $next);
    }
}
