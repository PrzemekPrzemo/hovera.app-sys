<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Models\Central\SystemSetting;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Payment;
use App\Observers\HorseObserver;
use App\Observers\PaymentObserver;
use App\Services\Billing\PayUService;
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

        // Central PayU — analogiczne do Przelewy24Service ale z OAuth2
        // client_credentials flow. Patrz docs/TRANSPORT.md §16.
        $this->app->singleton(PayUService::class, function ($app) {
            $cfg = $app['config']->get('services.payu', []);

            return new PayUService(
                posId: (int) ($cfg['pos_id'] ?? 0),
                oauthClientId: (string) ($cfg['oauth_client_id'] ?? ''),
                oauthClientSecret: (string) ($cfg['oauth_client_secret'] ?? ''),
                md5Key: (string) ($cfg['md5_key'] ?? ''),
                secondKey: (string) ($cfg['second_key'] ?? ''),
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

        // SMTP config override z SystemSetting (zarządzane przez master admina
        // w /admin/smtp-settings). Pierwszeństwo nad .env. Pozwala rotować
        // creds bez SSH. Bezpiecznie pomijane gdy DB nie zhydratowane (np.
        // route:list w boot — SystemSetting::getValue ma try/catch w środku).
        $this->overrideMailConfigFromSystemSettings();
    }

    /**
     * Hydruje config('mail.mailers.{smtp,transport}.*') + config('mail.from')
     * z SystemSetting jeśli master admin ustawił. Bez zmian gdy SystemSetting
     * pusty — config zostaje z .env (legacy fallback).
     */
    private function overrideMailConfigFromSystemSettings(): void
    {
        // Default mailer (smtp) — master admin / password reset / system notifs
        $host = SystemSetting::getSecret('mail.default.host');
        if ($host !== null && $host !== '') {
            config([
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) SystemSetting::getValue('mail.default.port', 587),
                'mail.mailers.smtp.username' => SystemSetting::getSecret('mail.default.username'),
                'mail.mailers.smtp.password' => SystemSetting::getSecret('mail.default.password'),
                'mail.mailers.smtp.encryption' => SystemSetting::getValue('mail.default.encryption', 'tls') === 'null'
                    ? null
                    : SystemSetting::getValue('mail.default.encryption', 'tls'),
            ]);
            $fromAddress = SystemSetting::getValue('mail.default.from_address');
            $fromName = SystemSetting::getValue('mail.default.from_name');
            if ($fromAddress) {
                config(['mail.from.address' => $fromAddress]);
            }
            if ($fromName) {
                config(['mail.from.name' => $fromName]);
            }
        }

        // Transport mailer (dedicated dla emaili wychodzących z modułu transport
        // — oferty, dispatcher, recenzje). Osobna konfig żeby separacja
        // reputacji domeny.
        $transportHost = SystemSetting::getSecret('mail.transport.host');
        if ($transportHost !== null && $transportHost !== '') {
            config([
                'mail.mailers.transport.host' => $transportHost,
                'mail.mailers.transport.port' => (int) SystemSetting::getValue('mail.transport.port', 587),
                'mail.mailers.transport.username' => SystemSetting::getSecret('mail.transport.username'),
                'mail.mailers.transport.password' => SystemSetting::getSecret('mail.transport.password'),
                'mail.mailers.transport.encryption' => SystemSetting::getValue('mail.transport.encryption', 'tls') === 'null'
                    ? null
                    : SystemSetting::getValue('mail.transport.encryption', 'tls'),
            ]);
            $transportFromAddress = SystemSetting::getValue('mail.transport.from_address');
            $transportFromName = SystemSetting::getValue('mail.transport.from_name');
            if ($transportFromAddress) {
                config(['mail.mailers.transport.from.address' => $transportFromAddress]);
            }
            if ($transportFromName) {
                config(['mail.mailers.transport.from.name' => $transportFromName]);
            }
        }
    }
}
