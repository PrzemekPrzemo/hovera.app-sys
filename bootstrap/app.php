<?php

declare(strict_types=1);

use App\Http\Middleware\HydrateTenantConnectionFromSession;
use App\Http\Middleware\ResolveTenantByCustomDomain;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Payment webhooks come from third-party providers (Stripe, Mollie,
        // P24, PayU) — they don't carry our CSRF token. Each provider's
        // implementation verifies its own signature header instead.
        //
        // Use env() not config() — this callback runs during HttpKernel
        // resolution, BEFORE LoadConfiguration bootstrapper registers the
        // 'config' service. Calling config() here throws "Target class
        // [config] does not exist" on every HTTP request.
        $publicPrefix = env('HOVERA_PUBLIC_SITE_PREFIX', 's');
        $middleware->validateCsrfTokens(except: [
            $publicPrefix.'/*/payments/*/webhook',
            // Central hovera Stripe billing — signature header is the auth.
            'webhooks/stripe',
        ]);

        // Hydrate tenant connection ze sesji na KAŻDYM web request (nie
        // tylko panel auth). Konieczne żeby Livewire endpoints (np.
        // /livewire/update) miały tenant connection — bez tego pluck()
        // na BoardingService etc. wybucha z "Access denied for ''@'localhost'".
        // Vanity domain resolver runs FIRST in the global stack — it
        // rewrites the request path before route matching, so the
        // public site / portal / booking respond at e.g.
        // https://mojastajnia.pl/ instead of /s/mojastajnia/.
        $middleware->prepend(ResolveTenantByCustomDomain::class);

        $middleware->web(append: [
            SetLocale::class,
            HydrateTenantConnectionFromSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
