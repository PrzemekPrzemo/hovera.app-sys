<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\SystemSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\TestCase;

/**
 * Pokrywa skip_tls_verify flag — gdy shared hosting (np. lh.pl) serwuje
 * wildcard cert na innej domenie niż SMTP host, default TLS handshake
 * failuje "peer certificate CN did not match expected CN". Toggle w UI
 * pozwala master adminowi obejść to (z downgradem ochrony MITM).
 */
class SmtpSkipTlsVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_tls_verify_propagates_from_system_setting_to_config(): void
    {
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        SystemSetting::setValue('mail.default.skip_tls_verify', true);

        $method = new ReflectionMethod(AppServiceProvider::class, 'overrideMailConfigFromSystemSettings');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $this->assertTrue(config('mail.mailers.smtp.skip_tls_verify'));
    }

    public function test_skip_tls_verify_defaults_to_false_when_unset(): void
    {
        SystemSetting::setSecret('mail.default.host', 'smtp.example.com');
        // Nie ustawiamy skip_tls_verify → powinno być false default.

        $method = new ReflectionMethod(AppServiceProvider::class, 'overrideMailConfigFromSystemSettings');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $this->assertFalse(config('mail.mailers.smtp.skip_tls_verify'));
    }

    public function test_transport_mailer_has_independent_skip_tls_verify(): void
    {
        SystemSetting::setSecret('mail.transport.host', 'smtp.transport-only.example.com');
        SystemSetting::setValue('mail.transport.skip_tls_verify', true);

        $method = new ReflectionMethod(AppServiceProvider::class, 'overrideMailConfigFromSystemSettings');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $this->assertTrue(config('mail.mailers.transport.skip_tls_verify'));
    }

    public function test_mail_extend_callback_applies_stream_options_when_skip_tls_verify_enabled(): void
    {
        // Symulujemy resolved transport via Mail::extend factory hook.
        // Wywołujemy registerSmtpStreamOptionsExtension() i przez Mail::extend
        // pobieramy mailer'a 'smtp' z konfigiem skip_tls_verify=true.
        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.galoptrans.pl',
                'port' => 465,
                'encryption' => 'tls',
                'username' => 'hi@galoptrans.pl',
                'password' => 'fake-secret',
                'skip_tls_verify' => true,
                'timeout' => 60,
            ],
        ]);

        $method = new ReflectionMethod(AppServiceProvider::class, 'registerSmtpStreamOptionsExtension');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertInstanceOf(
            EsmtpTransport::class,
            $transport,
        );

        // Stream powinien być SocketStream z ssl options.
        $stream = $transport->getStream();
        $this->assertInstanceOf(
            SocketStream::class,
            $stream,
        );

        // Reflection na private $streamContextOptions
        $reflection = new \ReflectionProperty($stream, 'streamContextOptions');
        $reflection->setAccessible(true);
        $options = $reflection->getValue($stream);

        $this->assertArrayHasKey('ssl', $options);
        $this->assertFalse($options['ssl']['verify_peer']);
        $this->assertFalse($options['ssl']['verify_peer_name']);
        $this->assertTrue($options['ssl']['allow_self_signed']);
    }

    public function test_mail_extend_callback_does_not_modify_stream_when_skip_tls_verify_disabled(): void
    {
        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user',
                'password' => 'pass',
                'skip_tls_verify' => false,
                'timeout' => 60,
            ],
        ]);

        $method = new ReflectionMethod(AppServiceProvider::class, 'registerSmtpStreamOptionsExtension');
        $method->setAccessible(true);
        $method->invoke($this->app->make(AppServiceProvider::class, ['app' => $this->app]));

        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $stream = $transport->getStream();

        $reflection = new \ReflectionProperty($stream, 'streamContextOptions');
        $reflection->setAccessible(true);
        $options = $reflection->getValue($stream);

        // Brak naszej ssl override gdy skip_tls_verify=false.
        $this->assertArrayNotHasKey('ssl', $options);
    }
}
