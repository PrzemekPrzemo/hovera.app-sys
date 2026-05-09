<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant\Horse;
use App\Models\Tenant\Payment;
use App\Observers\HorseObserver;
use App\Observers\PaymentObserver;
use App\Services\Billing\StripeBillingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Central hovera billing — Stripe Checkout + Customer Portal.
        // Bound as singleton so the Stripe SDK client gets reused across
        // the request. Tests can override via $this->app->bind() with a mock.
        $this->app->singleton(StripeBillingService::class, function ($app) {
            $config = $app['config']->get('services.stripe', []);

            return new StripeBillingService(
                secret: (string) ($config['secret'] ?? ''),
                webhookSecret: $config['webhook']['secret'] ?? null,
                webhookTolerance: (int) ($config['webhook']['tolerance'] ?? 300),
            );
        });
    }

    public function boot(): void
    {
        // Payment observer: gdy provider webhook ustawi status=succeeded,
        // automatycznie marks linked Invoice jako paid.
        Payment::observe(PaymentObserver::class);

        // Horse observer: synchronizuje historię BoxAssignment gdy
        // Filament resource zmienia horses.box_id przez form save.
        Horse::observe(HorseObserver::class);
    }
}
