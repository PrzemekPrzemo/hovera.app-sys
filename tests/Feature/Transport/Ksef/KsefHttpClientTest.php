<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Ksef;

use App\Domain\Transport\Ksef\Api\KsefApiException;
use App\Domain\Transport\Ksef\Api\KsefHttpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unit tests dla low-level klienta KSeF API. Sprawdza:
 *   - struktura request bodies (contextIdentifier, XML, SessionToken header)
 *   - exception mapping (KsefApiException dla 4xx/5xx)
 *   - public key resolution (storage > config > throw)
 */
class KsefHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->publishFakePublicKey('test');
    }

    public function test_authorization_challenge_sends_correct_context_identifier(): void
    {
        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response([
                'challenge' => 'CHL-001',
                'timestamp' => '2026-05-19T10:00:00Z',
            ], 200),
        ]);

        $client = new KsefHttpClient;
        $result = $client->getAuthorizationChallenge('test', '1234567890');

        $this->assertSame('CHL-001', $result['challenge']);
        $this->assertSame('2026-05-19T10:00:00Z', $result['timestamp']);

        Http::assertSent(function ($request) {
            $body = json_decode((string) $request->body(), true);

            return is_array($body)
                && ($body['contextIdentifier']['type'] ?? null) === 'onip'
                && ($body['contextIdentifier']['identifier'] ?? null) === '1234567890';
        });
    }

    public function test_authorization_challenge_normalizes_nip_strips_non_digits(): void
    {
        Http::fake([
            '*' => Http::response(['challenge' => 'x', 'timestamp' => '2026-05-19T10:00:00Z'], 200),
        ]);

        (new KsefHttpClient)->getAuthorizationChallenge('test', '123-456-78-90');

        Http::assertSent(function ($request) {
            $body = json_decode((string) $request->body(), true);

            return ($body['contextIdentifier']['identifier'] ?? null) === '1234567890';
        });
    }

    public function test_authorization_challenge_throws_on_http_error(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'invalid'], 400),
        ]);

        $this->expectException(KsefApiException::class);
        (new KsefHttpClient)->getAuthorizationChallenge('test', '1234567890');
    }

    public function test_init_session_token_posts_xml_to_init_endpoint(): void
    {
        Http::fake([
            '*/Session/InitToken' => Http::response([
                'sessionToken' => [
                    'token' => 'session-XYZ',
                    'expirationDate' => '2026-05-19T13:00:00Z',
                ],
            ], 200),
        ]);

        $client = new KsefHttpClient;
        $result = $client->initSessionTokenWithToken(
            environment: 'test',
            nip: '1234567890',
            authToken: 'auth-tok',
            challenge: 'CHL-001',
            timestamp: '2026-05-19T10:00:00Z',
        );

        $this->assertSame('session-XYZ', $result['session_token']);
        $this->assertSame(32, strlen($result['aes_key']), 'AES key should be 256-bit (32 bytes)');

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            return str_contains($request->url(), '/Session/InitToken')
                && str_contains($body, '<ns:InitSessionTokenRequest')
                && str_contains($body, '<ns:Challenge>CHL-001</ns:Challenge>')
                && str_contains($body, '<ns:Token>')
                && str_contains($body, '<ns:EncryptionKey>');
        });
    }

    public function test_init_session_token_throws_when_public_key_missing(): void
    {
        Storage::disk('local')->delete('ksef/public-key-test.pem');

        $this->expectException(\RuntimeException::class);
        (new KsefHttpClient)->initSessionTokenWithToken(
            environment: 'test',
            nip: '1234567890',
            authToken: 'tok',
            challenge: 'CHL',
            timestamp: '2026-05-19T10:00:00Z',
        );
    }

    public function test_send_invoice_includes_session_token_header_and_encrypted_body(): void
    {
        Http::fake([
            '*/Invoice/Send' => Http::response(['elementReferenceNumber' => 'REF-9'], 200),
        ]);

        $aesKey = random_bytes(32);
        $xml = '<?xml version="1.0"?><Faktura/>';

        $result = (new KsefHttpClient)->sendInvoice('test', 'sess-tok-1', $aesKey, $xml);

        $this->assertSame('REF-9', $result['element_reference_number']);

        Http::assertSent(function ($request) use ($xml) {
            return str_contains($request->url(), '/Invoice/Send')
                && $request->hasHeader('SessionToken', 'sess-tok-1')
                // Body powinno być binarne (IV + ciphertext), NIE plaintextem XML.
                && ! str_contains((string) $request->body(), $xml);
        });
    }

    public function test_send_invoice_throws_when_response_lacks_reference(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true], 200), // no elementReferenceNumber
        ]);

        $this->expectException(KsefApiException::class);
        (new KsefHttpClient)->sendInvoice('test', 'sess', random_bytes(32), '<xml/>');
    }

    public function test_get_invoice_status_returns_processing_code_mapping(): void
    {
        Http::fake([
            '*/Invoice/Status/*' => Http::response([
                'processingCode' => '200',
                'processingDescription' => 'Accepted',
                'ksefReferenceNumber' => 'KSEF-FINAL-1',
            ], 200),
        ]);

        $result = (new KsefHttpClient)->getInvoiceStatus('test', 'sess-tok', 'REF-123');

        $this->assertSame('200', $result['processing_code']);
        $this->assertSame('Accepted', $result['processing_description']);
        $this->assertSame('KSEF-FINAL-1', $result['ksef_reference_number']);

        Http::assertSent(fn ($r) => $r->hasHeader('SessionToken', 'sess-tok')
            && str_contains($r->url(), '/Invoice/Status/REF-123'));
    }

    public function test_host_for_returns_correct_environment_hosts(): void
    {
        $client = new KsefHttpClient;
        $this->assertSame(KsefHttpClient::HOST_TEST, $client->hostFor('test'));
        $this->assertSame(KsefHttpClient::HOST_DEMO, $client->hostFor('demo'));
        $this->assertSame(KsefHttpClient::HOST_PROD, $client->hostFor('production'));
        $this->assertSame(KsefHttpClient::HOST_PROD, $client->hostFor('prod'));
        // Unknown env defaults to test (safest).
        $this->assertSame(KsefHttpClient::HOST_TEST, $client->hostFor('unknown'));
    }

    public function test_public_key_resolves_from_inline_config_when_storage_empty(): void
    {
        Storage::disk('local')->delete('ksef/public-key-demo.pem');

        $keyConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($keyConfig);
        $details = openssl_pkey_get_details($res);

        config()->set('services.ksef.public_key.demo_pem', $details['key']);

        $pem = (new KsefHttpClient)->getPublicKey('demo');
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $pem);
        // Powinno też zapisać do storage cache.
        $this->assertTrue(Storage::disk('local')->exists('ksef/public-key-demo.pem'));
    }

    private function publishFakePublicKey(string $env): void
    {
        $keyConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($keyConfig);
        $details = openssl_pkey_get_details($res);
        Storage::disk('local')->put('ksef/public-key-'.$env.'.pem', $details['key']);
    }
}
