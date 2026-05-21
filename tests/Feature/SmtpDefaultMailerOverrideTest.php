<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\SystemSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pokrywa override mail.default = 'smtp' w AppServiceProvider gdy
 * SystemSetting `mail.default.host` jest ustawione. Wcześniej config
 * override ustawiał tylko mail.mailers.smtp.* — mail.default pozostawał
 * env('MAIL_MAILER', 'log') czyli maile lądowały w logu mimo zapisanego
 * SMTP w admin UI.
 */
class SmtpDefaultMailerOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mailer_switches_to_smtp_when_system_setting_has_host(): void
    {
        // Symulujemy stan po Save w /admin/smtp-settings — SystemSetting ma host.
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setValue('mail.default.port', 587);
        SystemSetting::setSecret('mail.default.username', 'user@example.com');
        SystemSetting::setSecret('mail.default.password', 'secret');
        SystemSetting::setValue('mail.default.encryption', 'tls');

        // Pre-override: symulujemy .env = log (najbardziej common production miss).
        config(['mail.default' => 'log']);
        config(['mail.mailers.smtp.host' => null]);

        // Wywołujemy bezpośrednio metodę override (normalnie boot wywołuje).
        $method = new ReflectionMethod(AppServiceProvider::class, 'overrideMailConfigFromSystemSettings');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $this->assertSame('smtp', config('mail.default'),
            'mail.default MUSI być przełączone na smtp gdy SystemSetting ma host — bez tego maile lecą do log/array.');
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('user@example.com', config('mail.mailers.smtp.username'));
    }

    public function test_default_mailer_unchanged_when_system_setting_empty(): void
    {
        // Brak konfiguracji UI → respect .env value.
        config(['mail.default' => 'log']);

        $method = new ReflectionMethod(AppServiceProvider::class, 'overrideMailConfigFromSystemSettings');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $this->assertSame('log', config('mail.default'),
            'Bez SystemSetting host, mail.default zostaje jak w .env (no auto-magic).');
    }

    public function test_from_address_and_name_overrides(): void
    {
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setValue('mail.default.from_address', 'hello@hovera.app');
        SystemSetting::setValue('mail.default.from_name', 'Hovera Custom');

        config(['mail.default' => 'log']);
        config(['mail.from.address' => 'old@old.test']);
        config(['mail.from.name' => 'Old']);

        $method = new ReflectionMethod(AppServiceProvider::class, 'overrideMailConfigFromSystemSettings');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $this->assertSame('hello@hovera.app', config('mail.from.address'));
        $this->assertSame('Hovera Custom', config('mail.from.name'));
    }
}
