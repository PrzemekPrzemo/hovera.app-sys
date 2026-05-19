<?php

declare(strict_types=1);

use App\Http\Middleware\Api\ApiAuthenticateAndResolveTenant;
use App\Http\Middleware\Api\ApiRateLimit;
use App\Http\Middleware\Api\RequireRole;
use App\Http\Middleware\HydrateTenantConnectionFromSession;
use App\Http\Middleware\ResolveTenantByCustomDomain;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $publicPrefix = env('HOVERA_PUBLIC_SITE_PREFIX', 's');
        $middleware->validateCsrfTokens(except: [
            $publicPrefix.'/*/payments/*/webhook',
            'webhooks/stripe',
            'webhooks/stripe-connect',
            'webhooks/przelewy24',
            // Per-transporter P24 quote webhook + central P24 add-on webhook —
            // patrz docs/TRANSPORT.md §15.5 i §13. Sign weryfikowany wewnątrz
            // kontrolerów.
            'transport/p24/webhook/*',
            'webhooks/przelewy24/addon',
            // PayU webhooks (central invoices + add-ons + per-transporter quote).
            // Signature SHA256 z raw body + md5_key weryfikowany wewnątrz
            // PayUService::verifyWebhookSignature. Patrz docs/TRANSPORT.md §16.
            'webhooks/payu',
            'webhooks/payu/addon',
            'transport/payu/webhook/*',
            'api/*',
        ]);

        $middleware->prepend(ResolveTenantByCustomDomain::class);

        $middleware->web(append: [
            SetLocale::class,
            HydrateTenantConnectionFromSession::class,
        ]);

        // Stateless API stack: token auth (Sanctum) + tenant resolution
        // is performed inside ApiAuthenticateAndResolveTenant — it accepts
        // a Bearer token, looks up the user via Sanctum's PersonalAccessToken
        // model, validates X-Tenant-Id against tenant_memberships, and then
        // calls TenantManager::setCurrent so tenant queries route correctly.
        $middleware->api(append: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'api.tenant' => ApiAuthenticateAndResolveTenant::class,
            'api.role' => RequireRole::class,
            'api.throttle' => ApiRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
