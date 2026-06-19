<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\SystemSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pokrywa override Mailgun creds z `/admin/smtp-settings` (sekcja "Mailgun API").
 * Reguły:
 *   - Mailgun wygrywa nad SMTP gdy `domain` AND `secret` są ustawione w
 *     SystemSetting → `config('mail.default') = 'mailgun'`
 *   - sam endpoint (bez secret/domain) NIE przełącza mailera (potrzeba kompletu)
 *   - endpoint defaultuje do `api.eu.mailgun.net` (EU region — Hovera)
 *   - From address/name z `mail.default.from_*` są reużywane (jeden globalny From)
 *   - bez Mailgun creds — SMTP path (host) działa jak wcześniej
 */
class SmtpDefaultMailerMailgunOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sanity reset — testy SystemSetting per-test (RefreshDatabase czyści tabelę).
        config(['mail.default' => 'log']);
    }

    public function test_mailgun_creds_switch_default_mailer_to_mailgun(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-test-fake');

        $this->invokeOverride();

        $this->assertSame('mailgun', config('mail.default'));
        $this->assertSame('hovera.pl', config('services.mailgun.domain'));
        $this->assertSame('key-test-fake', config('services.mailgun.secret'));
        $this->assertSame('api.eu.mailgun.net', config('services.mailgun.endpoint'));
    }

    public function test_custom_endpoint_persists(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-test-fake');
        SystemSetting::setValue('mail.mailgun.endpoint', 'api.mailgun.net'); // US override

        $this->invokeOverride();

        $this->assertSame('api.mailgun.net', config('services.mailgun.endpoint'));
    }

    public function test_only_secret_without_domain_does_not_switch_mailer(): void
    {
        // Połowiczna konfiguracja — Mailgun transport wymaga obu pól, więc
        // nie przełączamy default mailera. Spadamy do SMTP path / .env fallback.
        SystemSetting::setSecret('mail.mailgun.secret', 'key-test-fake');

        $this->invokeOverride();

        $this->assertNotSame('mailgun', config('mail.default'));
    }

    public function test_only_domain_without_secret_does_not_switch_mailer(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');

        $this->invokeOverride();

        $this->assertNotSame('mailgun', config('mail.default'));
    }

    public function test_mailgun_reuses_from_address_from_default_mailer(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-test-fake');
        SystemSetting::setValue('mail.default.from_address', 'noreply@hovera.pl');
        SystemSetting::setValue('mail.default.from_name', 'Hovera');

        $this->invokeOverride();

        $this->assertSame('noreply@hovera.pl', config('mail.from.address'));
        $this->assertSame('Hovera', config('mail.from.name'));
    }

    public function test_smtp_path_still_works_when_mailgun_unset(): void
    {
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setSecret('mail.default.username', 'user');
        SystemSetting::setSecret('mail.default.password', 'pass');

        $this->invokeOverride();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
    }

    private function invokeOverride(): void
    {
        $provider = new AppServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, 'overrideMailConfigFromSystemSettings');
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }
}
