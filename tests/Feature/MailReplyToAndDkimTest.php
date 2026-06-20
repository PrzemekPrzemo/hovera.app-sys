<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\SystemSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Pokrywa:
 *   - Reply-To bug fix: po wpisaniu reply_to_address w /admin/smtp-settings
 *     wychodzący mail (Mail::raw) faktycznie ma header Reply-To (wcześniej
 *     bug — alwaysReplyTo wołane PRZED config overrides → singleton cached
 *     ze starym configiem, Mail::raw resolveował świeży mailer bez replyTo).
 *   - DKIM signing: gdy admin wpisał DKIM private key + domain + selector,
 *     wychodzący mail ma header DKIM-Signature.
 */
class MailReplyToAndDkimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
    }

    public function test_reply_to_is_applied_to_test_email(): void
    {
        SystemSetting::setValue('mail.default.reply_to_address', 'kontakt@hovera.pl');
        SystemSetting::setValue('mail.default.reply_to_name', 'Hovera Wsparcie');

        $this->invokeOverride();

        Mail::raw('test', function ($m) {
            $m->to('user@example.com')->subject('Test');
        });

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        /** @var Email $mime */
        $mime = $messages->first()->getOriginalMessage();
        $replyTo = $mime->getReplyTo();
        $this->assertNotEmpty($replyTo, 'Reply-To header should be present');
        $this->assertSame('kontakt@hovera.pl', $replyTo[0]->getAddress());
    }

    public function test_dkim_header_is_added_when_keys_configured(): void
    {
        // RSA private key wygenerowany dla testu — 2048 bit, PKCS#8.
        // (Generated z openssl genrsa -out /dev/stdout 2048 + przekonwertowane.)
        $privateKey = $this->generateTestPrivateKey();

        SystemSetting::setSecret('mail.dkim.private_key', $privateKey);
        SystemSetting::setValue('mail.dkim.domain', 'hovera.pl');
        SystemSetting::setValue('mail.dkim.selector', 'mailo');

        $this->invokeOverride();

        Mail::raw('test body', function ($m) {
            $m->to('user@example.com')->from('noreply@hovera.pl')->subject('Test');
        });

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        /** @var Email $mime */
        $mime = $messages->first()->getOriginalMessage();
        $dkimHeader = $mime->getHeaders()->get('DKIM-Signature');
        $this->assertNotNull($dkimHeader, 'DKIM-Signature header should be present');
        $headerValue = (string) $dkimHeader->getBodyAsString();
        $this->assertStringContainsString('d=hovera.pl', $headerValue);
        $this->assertStringContainsString('s=mailo', $headerValue);
    }

    public function test_dkim_skipped_when_keys_missing(): void
    {
        // Brak konfiguracji DKIM → żaden header nie powinien być dodany.
        $this->invokeOverride();

        Mail::raw('test', function ($m) {
            $m->to('user@example.com')->from('noreply@hovera.pl')->subject('Test');
        });

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        /** @var Email $mime */
        $mime = $messages->first()->getOriginalMessage();
        $this->assertNull($mime->getHeaders()->get('DKIM-Signature'));
    }

    private function invokeOverride(): void
    {
        $provider = new AppServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, 'overrideMailConfigFromSystemSettings');
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    private function generateTestPrivateKey(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $pem);

        return (string) $pem;
    }
}
