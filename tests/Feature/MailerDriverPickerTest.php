<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\SystemSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pokrywa explicit driver picker z `/admin/smtp-settings`.
 * Setting `mail.default.driver` może być: '' / 'auto' / 'mailgun' / 'smtp' / 'log'.
 *
 *   auto         → detekcja (Mailgun jeśli creds set, inaczej SMTP, inaczej .env)
 *   mailgun      → wymuszony Mailgun (fallback do auto-path gdy brak creds)
 *   smtp         → wymuszony SMTP (fallback do auto-path gdy brak hosta)
 *   log          → wymuszony 'log' (maile NIE wychodzą — debug / maintenance)
 *
 * Stary behavior (pre-picker) jest zachowany — pusty `default_driver` = 'auto'.
 */
class MailerDriverPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'log']);
    }

    public function test_log_driver_forces_log_even_when_mailgun_configured(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-fake');
        SystemSetting::setValue('mail.default.driver', 'log');

        $this->invokeOverride();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_auto_mode_uses_mailgun_when_creds_present(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-fake');
        SystemSetting::setValue('mail.default.driver', 'auto');

        $this->invokeOverride();

        $this->assertSame('mailgun', config('mail.default'));
    }

    public function test_empty_driver_is_treated_as_auto(): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-fake');
        SystemSetting::setValue('mail.default.driver', '');

        $this->invokeOverride();

        $this->assertSame('mailgun', config('mail.default'));
    }

    public function test_smtp_forces_smtp_path_even_with_mailgun_creds(): void
    {
        // Master admin ma OBA skonfigurowane, ale wybrał SMTP w UI.
        // Pomijamy Mailgun branch, idziemy do SMTP.
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-fake');
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setSecret('mail.default.username', 'user');
        SystemSetting::setSecret('mail.default.password', 'pass');
        SystemSetting::setValue('mail.default.driver', 'smtp');

        $this->invokeOverride();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
    }

    public function test_forced_mailgun_without_creds_falls_back_to_smtp_or_env(): void
    {
        // forced=mailgun ale brak Mailgun creds — auto fallback do SMTP (host set)
        // żeby admin'owi nie crashować maila do null transport'a.
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setSecret('mail.default.username', 'user');
        SystemSetting::setSecret('mail.default.password', 'pass');
        SystemSetting::setValue('mail.default.driver', 'mailgun');

        $this->invokeOverride();

        $this->assertSame('smtp', config('mail.default'));
    }

    public function test_forced_smtp_without_host_does_not_break(): void
    {
        // Brak host'a + brak Mailgun creds + forced=smtp → padamy do .env (log).
        // Najgorszy przypadek ale nie crashuje aplikacji.
        SystemSetting::setValue('mail.default.driver', 'smtp');

        $this->invokeOverride();

        // Pierwsze setUp ustawiło 'mail.default' = 'log' — override nic nie zrobił.
        $this->assertSame('log', config('mail.default'));
    }

    public function test_auto_mode_falls_back_to_smtp_when_no_mailgun(): void
    {
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setSecret('mail.default.username', 'user');
        SystemSetting::setSecret('mail.default.password', 'pass');
        // brak `mail.default.driver` → default auto

        $this->invokeOverride();

        $this->assertSame('smtp', config('mail.default'));
    }

    private function invokeOverride(): void
    {
        $provider = new AppServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, 'overrideMailConfigFromSystemSettings');
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }
}
