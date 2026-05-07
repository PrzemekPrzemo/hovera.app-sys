<?php

declare(strict_types=1);

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
