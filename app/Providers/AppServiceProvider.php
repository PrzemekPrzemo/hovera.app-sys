<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Models\Central\SystemSetting;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Payment;
use App\Observers\HorseObserver;
use App\Observers\PaymentObserver;
use App\Services\Billing\Przelewy24Service;
use App\Services\Billing\StripeBillingService;
use App\Services\Integrations\LiveJumping\LiveJumpingClient;
use App\Services\Integrations\LiveJumping\LiveJumpingFeatureGate;
use App\Services\Integrations\TodoistClient;
use App\Services\Ksef\CentralKsefService;
use App\Services\TenantAuditLogger;
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

        // Stripe Connect Express — per-transporter direct charges (NIE
        // pomyl z central StripeBillingService — to inny scope: connected
        // accounts, nie platform account). Patrz docs/TRANSPORT.md §15.6.
        $this->app->singleton(TransporterStripeConnectService::class, function ($app) {
            $stripe = $app['config']->get('services.stripe', []);
            $connect = $stripe['connect'] ?? [];

            return new TransporterStripeConnectService(
                secret: (string) ($stripe['secret'] ?? ''),
                country: (string) ($connect['country'] ?? 'PL'),
                applicationFeePercent: (float) ($connect['application_fee_percent'] ?? 0),
                audit: $app->make(TenantAuditLogger::class),
            );
        });

        // Central P24 — hovera SaaS one-time payments. Singleton bo
        // konfiguracja jest globalna i nie zmienia się per-request.
        $this->app->singleton(Przelewy24Service::class, function ($app) {
            $cfg = $app['config']->get('services.przelewy24', []);

            return new Przelewy24Service(
                merchantId: (int) ($cfg['merchant_id'] ?? 0),
                posId: (int) ($cfg['pos_id'] ?? ($cfg['merchant_id'] ?? 0)),
                apiKey: (string) ($cfg['api_key'] ?? ''),
                crc: (string) ($cfg['crc'] ?? ''),
                env: (string) ($cfg['env'] ?? 'sandbox'),
            );
        });

        // Central KSeF — żaden konstruktor argumentów nie potrzebuje
        // (cert/NIP czyta z SystemSetting), ale singleton pozwala
        // testom go zmockować via $this->app->instance().
        $this->app->singleton(CentralKsefService::class);

        // LiveJumping integration — partnerski feed wyników/kalendarza
        // zawodów. Feature gate + client jako singletony; client zaciąga
        // creds z SystemSetting w runtime (nie w konstruktorze) bo master
        // admin może je zmienić bez restartu workera.
        $this->app->singleton(LiveJumpingFeatureGate::class);
        $this->app->singleton(LiveJumpingClient::class);

        // In-panel bug reporter → Todoist. Singleton — config nie zmienia
        // się per-request, tests mockują via $this->app->instance().
        $this->app->singleton(TodoistClient::class, function ($app) {
            $cfg = $app['config']->get('services.todoist', []);

            return new TodoistClient(
                token: $cfg['token'] ?? null,
                projectId: (string) ($cfg['hovera_project_id'] ?? ''),
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
