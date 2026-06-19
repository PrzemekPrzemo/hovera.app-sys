<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Models\Central\SystemSetting;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Payment;
use App\Observers\HorseObserver;
use App\Observers\PaymentObserver;
use App\Observers\Tenant\HealthRecordObserver;
use App\Observers\Tenant\HorseFieldChangeObserver;
use App\Observers\Tenant\InvoiceObserver;
use App\Services\Billing\PayUService;
use App\Services\Billing\Przelewy24Service;
use App\Services\Billing\StripeBillingService;
use App\Services\Integrations\LiveJumping\LiveJumpingClient;
use App\Services\Integrations\LiveJumping\LiveJumpingFeatureGate;
use App\Services\Integrations\TodoistClient;
use App\Services\Ksef\CentralKsefService;
use App\Services\TenantAuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

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

        // Faza 6 PR 6.3 — approval flow dla zmian kluczowych pól konia
        // (name / passport_number / microchip). Stable updated → tworzy
        // pending request, owner accept/reject w panel'u.
        Horse::observe(HorseFieldChangeObserver::class);

        // Faza 6 PR 6.1 — Owner notifications hub:
        //   * Invoice issued → NewInvoiceForOwner (database + mail)
        //   * HealthRecord created (vet/dentist/farrier/...) →
        //     VetVisitRecordedForOwner (database + mail)
        // Soft-fail w obu (OwnerNotificationDispatcher loguje błędy
        // ale nie cofa głównej akcji).
        Invoice::observe(InvoiceObserver::class);
        HealthRecord::observe(HealthRecordObserver::class);

        // Named rate limiters — pozwalają na warunkowe limity per env
        // bez duplikowania logiki w route definitions. `throttle:NAME`
        // w route'cie używa ich tutaj zdefiniowanych.
        $this->registerRateLimiters();

        // SMTP config override z SystemSetting (zarządzane przez master admina
        // w /admin/smtp-settings). Pierwszeństwo nad .env. Pozwala rotować
        // creds bez SSH. Bezpiecznie pomijane gdy DB nie zhydratowane (np.
        // route:list w boot — SystemSetting::getValue ma try/catch w środku).
        $this->overrideMailConfigFromSystemSettings();

        // Hook do Mail::extend('smtp') żeby zastosować stream_options gdy
        // `skip_tls_verify=true` (shared hosting z wildcard cert na innej
        // domenie — np. lh.pl serwuje *.lh.pl na smtp.galoptrans.pl).
        // Bez tego TLS handshake failuje z "peer cert CN mismatch".
        $this->registerSmtpStreamOptionsExtension();

        // Performance monitoring — domyślnie OFF, włącz przez env (zobacz
        // docs/PERFORMANCE.md). Brak narzutu gdy wyłączone.
        $this->registerPerformanceMonitoring();
    }

    /**
     * Dwa opt-in flagi do tropienia performance issues bez instalowania
     * Telescope. Defaults OFF — zero kosztu w produkcji bez konfiguracji.
     *
     *   HOVERA_SLOW_QUERY_LOG_MS=100
     *     Loguje każde query > N ms (z connection + bindings) do default
     *     log channel. Włącz w prod tylko na czas debugu — log spam.
     *
     *   HOVERA_PREVENT_LAZY_LOADING=true
     *     Model::preventLazyLoading() — lazy load relacji rzuca exception
     *     zamiast po cichu odpalać N+1. Włącz lokalnie / w CI żeby wykryć
     *     brakujące `->with()` przed deployem. NIE włączaj w prod (user
     *     widzi 500 zamiast wolniejszej strony).
     */
    private function registerPerformanceMonitoring(): void
    {
        $thresholdMs = (int) env('HOVERA_SLOW_QUERY_LOG_MS', 0);
        if ($thresholdMs > 0) {
            DB::listen(function (QueryExecuted $query) use ($thresholdMs): void {
                if ($query->time < $thresholdMs) {
                    return;
                }
                Log::warning('Slow query', [
                    'connection' => $query->connectionName,
                    'time_ms' => round($query->time, 2),
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                ]);
            });
        }

        if (filter_var(env('HOVERA_PREVENT_LAZY_LOADING', false), FILTER_VALIDATE_BOOLEAN)) {
            Model::preventLazyLoading();
        }
    }

    /**
     * Named rate limiters z env-awareness — prod ścisły anti-abuse,
     * lokalne / staging luźne dla testowania.
     */
    private function registerRateLimiters(): void
    {
        // `transporter-onboarding` — POST /przewoznicy/dolacz (carrier
        // signup z 6 file uploads × 5MB). Prod: 3/h/IP (anti-abuse dla
        // bot scrapers, ale luźno tolerantne dla legit user'a który
        // poprawia formularz po validation error). Non-prod: 30/h.
        //
        // Wcześniej było 1/h prod — za agresywne, user dostawał 429 przy
        // 2. próbie po fixie formy + nie wiedział czy 1. submit zadziałał.
        // Custom response callback renderuje branded blade z instrukcją
        // zamiast bare HTTP 429 default page.
        //
        // Per-IP key — `by(request->ip())` jest defaultem dla `Limit::perHour()`.
        RateLimiter::for('transporter-onboarding', function (Request $request) {
            $perHour = app()->environment('production') ? 3 : 30;

            return Limit::perHour($perHour)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfterSec = (int) ($headers['Retry-After'] ?? 3600);

                    return response()->view(
                        'public.transport.onboarding-rate-limited',
                        ['retry_after_minutes' => max(1, (int) ceil($retryAfterSec / 60))],
                        429,
                        $headers,
                    );
                });
        });
    }

    /**
     * Hydruje config('mail.mailers.{smtp,transport}.*') + config('mail.from')
     * z SystemSetting jeśli master admin ustawił. Bez zmian gdy SystemSetting
     * pusty — config zostaje z .env (legacy fallback).
     */
    private function overrideMailConfigFromSystemSettings(): void
    {
        // Mailgun (API transport) — preferowany nad SMTP gdy master admin
        // skonfigurował creds w /admin/smtp-settings. Setting `mail.default
        // = 'mailgun'` musi iść TYLKO gdy zarówno `secret` jak i `domain` są
        // wpisane (oba wymagane przez symfony/mailgun-mailer transport).
        $mailgunSecret = SystemSetting::getSecret('mail.mailgun.secret');
        $mailgunDomain = SystemSetting::getSecret('mail.mailgun.domain');
        if ($mailgunSecret !== null && $mailgunSecret !== ''
            && $mailgunDomain !== null && $mailgunDomain !== '') {
            config([
                'mail.default' => 'mailgun',
                'services.mailgun.domain' => $mailgunDomain,
                'services.mailgun.secret' => $mailgunSecret,
                'services.mailgun.endpoint' => SystemSetting::getValue('mail.mailgun.endpoint', 'api.eu.mailgun.net'),
                'services.mailgun.scheme' => SystemSetting::getValue('mail.mailgun.scheme', 'https'),
            ]);
            // From address / name z mail.default.from_* — reużywamy żeby
            // user nie musiał wpisywać dwa razy.
            $fromAddress = SystemSetting::getValue('mail.default.from_address');
            $fromName = SystemSetting::getValue('mail.default.from_name');
            if ($fromAddress) {
                config(['mail.from.address' => $fromAddress]);
            }
            if ($fromName) {
                config(['mail.from.name' => $fromName]);
            }

            // Mailgun wygrywa — nie idziemy dalej do SMTP override'a.
            // Transport mailer (osobne creds) nadal może być skonfigurowany,
            // niżej.
            $this->overrideTransportMailerFromSystemSettings();

            return;
        }

        // Default mailer (smtp) — master admin / password reset / system notifs
        $host = SystemSetting::getSecret('mail.default.host');
        if ($host !== null && $host !== '') {
            config([
                // Critical: switch default mailer to 'smtp' żeby `Mail::raw()` /
                // notify() faktycznie wysyłały przez SMTP. Bez tego override'a
                // `config('mail.default') = env('MAIL_MAILER', 'log')` mógł
                // zostać 'log' i wszystkie maile lądowały w storage/logs/laravel.log
                // mimo że master admin skonfigurował SMTP w /admin/smtp-settings.
                // Patrz docs/SMTP-SETTINGS.md (jeśli istnieje) i PR #354.
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) SystemSetting::getValue('mail.default.port', 587),
                'mail.mailers.smtp.username' => SystemSetting::getSecret('mail.default.username'),
                'mail.mailers.smtp.password' => SystemSetting::getSecret('mail.default.password'),
                'mail.mailers.smtp.encryption' => SystemSetting::getValue('mail.default.encryption', 'tls') === 'null'
                    ? null
                    : SystemSetting::getValue('mail.default.encryption', 'tls'),
                // skip_tls_verify — używane przez SmtpTransportFactory hook
                // (Mail::extend) gdy hosting shared'owany serwuje wildcard
                // cert na innej domenie (np. *.lh.pl dla smtp.galoptrans.pl).
                'mail.mailers.smtp.skip_tls_verify' => (bool) SystemSetting::getValue('mail.default.skip_tls_verify', false),
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

        $this->overrideTransportMailerFromSystemSettings();
    }

    /**
     * Transport mailer (dedicated dla emaili wychodzących z modułu transport —
     * oferty, dispatcher, recenzje). Osobna konfig żeby separacja reputacji
     * domeny. Wywoływane zarówno z SMTP path jak i z mailgun path (transport
     * jest independent od default mailera).
     */
    private function overrideTransportMailerFromSystemSettings(): void
    {
        $transportHost = SystemSetting::getSecret('mail.transport.host');
        if ($transportHost === null || $transportHost === '') {
            return;
        }
        config([
            'mail.mailers.transport.host' => $transportHost,
            'mail.mailers.transport.port' => (int) SystemSetting::getValue('mail.transport.port', 587),
            'mail.mailers.transport.username' => SystemSetting::getSecret('mail.transport.username'),
            'mail.mailers.transport.password' => SystemSetting::getSecret('mail.transport.password'),
            'mail.mailers.transport.encryption' => SystemSetting::getValue('mail.transport.encryption', 'tls') === 'null'
                ? null
                : SystemSetting::getValue('mail.transport.encryption', 'tls'),
            'mail.mailers.transport.skip_tls_verify' => (bool) SystemSetting::getValue('mail.transport.skip_tls_verify', false),
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

    /**
     * Hook do Mail::extend('smtp') wstrzykujący `stream_options` gdy
     * config zawiera `skip_tls_verify=true`. Replikuje default factory
     * Laravel'a + dorzuca `verify_peer=false` / `verify_peer_name=false`
     * / `allow_self_signed=true` na SocketStream PRZED nawiązaniem połączenia.
     *
     * Kiedy używać: hosting shared serwuje wildcard cert na innej domenie
     * niż SMTP host (np. `*.lh.pl` dla `smtp.galoptrans.pl`). Domyślny TLS
     * handshake failuje "peer cert CN mismatch".
     *
     * Trade-off security: skip_tls_verify DOWNGRADE'uje ochronę MITM. Akceptowalne
     * dla maili transactional gdy hosting fizycznie kontrolowany; nie używać
     * dla mailerów publicznych.
     */
    private function registerSmtpStreamOptionsExtension(): void
    {
        Mail::extend('smtp', function (array $config) {
            // Replicate Laravel default smtp factory (MailManager::createSmtpTransport).
            // Scheme: 'smtp' lub 'smtps'. Empty string crashuje
            // EsmtpTransportFactory („The \"\" scheme is not supported”), więc
            // ZAWSZE fallback'ujemy do 'smtp' gdy nie wyderywowaliśmy 'smtps'.
            // Encryption=tls + port 465 = 'smtps', wszystko inne = 'smtp'
            // (Symfony przełącza na STARTTLS na podstawie portu/parametrów).
            $scheme = $config['scheme'] ?? null;
            if (! $scheme) {
                $scheme = ! empty($config['encryption']) && $config['encryption'] === 'tls'
                    && ((int) ($config['port'] ?? 587) === 465)
                    ? 'smtps'
                    : 'smtp';
            }

            $factory = new EsmtpTransportFactory;
            $transport = $factory->create(new Dsn(
                $scheme,
                $config['host'] ?? 'localhost',
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['port'] ?? null,
                $config,
            ));

            // Konfiguracja jak Laravel'a (timeout, local_domain) — kopia z
            // MailManager::configureSmtpTransport żeby zachować feature parity.
            $stream = $transport->getStream();
            $stream->setTimeout($config['timeout'] ?? 60);
            if (! empty($config['local_domain'])) {
                $transport->setLocalDomain($config['local_domain']);
            }

            // OUR ADDITION: stream_options gdy skip_tls_verify=true.
            if (! empty($config['skip_tls_verify'])
                && $stream instanceof SocketStream) {
                $stream->setStreamOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]);
            }

            return $transport;
        });
    }
}
