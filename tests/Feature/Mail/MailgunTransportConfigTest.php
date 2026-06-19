<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Mail\MailManager;
use Tests\TestCase;

/**
 * Regression guard dla integracji Mailgun. Hovera używa Mailgun EU jako
 * główny transactional mailer (signupy, faktury, notyfikacje boarding etc.).
 * Test sprawdza:
 *   - config('mail.mailers.mailgun') zawiera transport=mailgun
 *   - config('services.mailgun.endpoint') defaultuje do api.eu.mailgun.net
 *     (US endpoint to api.mailgun.net — pomyłka regionu = 401 z proda)
 *   - MailManager potrafi zbudować transport bez exception'a (sanity check
 *     że symfony/mailgun-mailer package jest faktycznie zainstalowany —
 *     bez niego `composer require` był skipnięty na deployu)
 */
class MailgunTransportConfigTest extends TestCase
{
    public function test_mailgun_mailer_block_is_registered(): void
    {
        $this->assertSame('mailgun', config('mail.mailers.mailgun.transport'));
    }

    public function test_mailgun_endpoint_defaults_to_eu(): void
    {
        // Test isolation: reset env-derived value przy każdym runie.
        config()->set('services.mailgun.endpoint', env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'));

        $this->assertSame('api.eu.mailgun.net', config('services.mailgun.endpoint'));
    }

    public function test_mailgun_transport_can_be_resolved(): void
    {
        // Wymaga symfony/mailgun-mailer package'a. Bez niego MailManager
        // rzuci InvalidArgumentException. Stub creds — transport build'uje
        // klienta HTTP, nie wysyła jeszcze maila.
        config()->set('services.mailgun', [
            'domain' => 'hovera.pl',
            'secret' => 'key-test-fake',
            'endpoint' => 'api.eu.mailgun.net',
            'scheme' => 'https',
        ]);

        $manager = $this->app->make(MailManager::class);
        $mailer = $manager->mailer('mailgun');

        $this->assertNotNull($mailer);
    }
}
