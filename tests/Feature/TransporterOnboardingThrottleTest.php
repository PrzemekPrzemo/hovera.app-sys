<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Pokrywa named rate limiter `transporter-onboarding` z AppServiceProvider.
 * Production = 1/h strict (anti-abuse 30MB file uploads), non-prod = 30/h
 * (testing flow przez developera bez czyszczenia cache).
 */
class TransporterOnboardingThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limiter_registered_with_name(): void
    {
        $resolver = RateLimiter::limiter('transporter-onboarding');
        $this->assertNotNull($resolver, 'Rate limiter `transporter-onboarding` should be registered');
    }

    public function test_non_production_environment_uses_higher_limit(): void
    {
        // Test environment defaults to 'testing' → non-prod → 30/h
        $this->app['env'] = 'testing';
        $resolver = RateLimiter::limiter('transporter-onboarding');
        $request = Request::create('/przewoznicy/dolacz', 'POST');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        /** @var Limit $limit */
        $limit = $resolver($request);

        $this->assertSame(30, $limit->maxAttempts);
        $this->assertSame(3600, $limit->decaySeconds);  // 60 min = 3600s
    }

    public function test_production_environment_uses_strict_limit(): void
    {
        // Override env do production
        $this->app['env'] = 'production';
        $resolver = RateLimiter::limiter('transporter-onboarding');
        $request = Request::create('/przewoznicy/dolacz', 'POST');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        /** @var Limit $limit */
        $limit = $resolver($request);

        // 3/h prod — wcześniej było 1/h, ale to powodowało że user dostawał
        // 429 przy 2. próbie po fixie formy. 3/h nadal blokuje bot scrapery
        // ale daje legit user'owi tolerancję na pomyłki w formularzu.
        $this->assertSame(3, $limit->maxAttempts);
        $this->assertSame(3600, $limit->decaySeconds);  // 60 min = 3600s
    }

    public function test_rate_limit_response_renders_branded_page(): void
    {
        $this->app['env'] = 'production';
        $resolver = RateLimiter::limiter('transporter-onboarding');
        $request = Request::create('/przewoznicy/dolacz', 'POST');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        /** @var Limit $limit */
        $limit = $resolver($request);

        $this->assertNotNull($limit->responseCallback, 'Custom 429 response callback musi być ustawiony — bez tego user widzi bare HTTP 429.');

        $response = ($limit->responseCallback)($request, ['Retry-After' => 1800]);

        $this->assertSame(429, $response->getStatusCode());
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Już wysłałeś zgłoszenie', $body);
        // 1800s = 30min — view powinno pokazać ~30 minut.
        $this->assertStringContainsString('30 minut', $body);
    }

    public function test_limit_keyed_per_ip_address(): void
    {
        $this->app['env'] = 'testing';
        $resolver = RateLimiter::limiter('transporter-onboarding');

        $r1 = Request::create('/przewoznicy/dolacz', 'POST');
        $r1->server->set('REMOTE_ADDR', '1.2.3.4');
        $r2 = Request::create('/przewoznicy/dolacz', 'POST');
        $r2->server->set('REMOTE_ADDR', '5.6.7.8');

        $limit1 = $resolver($r1);
        $limit2 = $resolver($r2);

        $this->assertNotSame($limit1->key, $limit2->key, 'Per-IP keys differ');
    }
}
