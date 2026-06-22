<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Wysoko-poziomowy klient KSeF API. Łączy:
 *   - KsefCertificateService (parsowanie zapisanego cert)
 *   - KsefSigningService (XAdES-BES dla AuthTokenRequest)
 *   - KsefInvoiceXmlBuilder (FA(3) XML)
 *   - HTTP wywołania do KSeF endpointów
 *
 * Ten PR wprowadza skeleton + auth flow (challenge + sign). Pełna
 * sesja invoice send (RSA-OAEP wrapped AES-256-CBC + multi-document
 * batch) trafi w PR 4b — wymaga session lifecycle, encryption layer,
 * status polling.
 *
 * Endpoint hosts:
 *   - test:  https://ksef-test.mf.gov.pl/api
 *   - demo:  https://ksef-demo.mf.gov.pl/api
 *   - prod:  https://ksef.mf.gov.pl/api
 */
class KsefClient
{
    public const HOST_TEST = 'https://ksef-test.mf.gov.pl/api';

    public const HOST_DEMO = 'https://ksef-demo.mf.gov.pl/api';

    public const HOST_PROD = 'https://ksef.mf.gov.pl/api';

    public function __construct(
        private readonly KsefSigningService $signer,
        private readonly KsefInvoiceXmlBuilder $xml,
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * Sprawdź czy stajnia ma skonfigurowany cert + NIP gotowe do KSeF.
     */
    public function isReady(Tenant $tenant): bool
    {
        $cfg = (array) (data_get($tenant->settings, 'ksef') ?? []);

        return ! empty($cfg['context_nip']) && ! empty($cfg['cert_format']);
    }

    /**
     * Buduje XML faktury (bez podpisu) — przyda się do podglądu w UI
     * zanim wyślemy do KSeF.
     */
    public function buildInvoiceXml(Invoice $invoice): string
    {
        return $this->xml->build($invoice);
    }

    /**
     * Pełny flow auth: GET /auth/challenge → sign AuthTokenRequest →
     * POST /auth/token. Zwraca session token gotowy do wysyłki faktur
     * (lub rzuca wyjątek z czytelnym komunikatem).
     *
     * Cache nie używamy — KSeF tokeny są krótko-żyjące i powiązane z
     * konkretną sesją, lepiej dostać świeży za każdym razem.
     *
     * Auth-only wariant — sessionToken otrzymany tym flow NIE służy do
     * wysyłki faktur (Invoice/Send wymaga AES encryption key, którego
     * tu nie negocjujemy). Use case: weryfikacja konfiguracji (`InvoiceResource::ksef`
     * action). Dla pełnego send/poll flow użyj `authenticateWithEncryptionKey`.
     */
    public function authenticate(Tenant $tenant): string
    {
        $result = $this->doAuthenticate($tenant, wrappedAesKeyBase64: null);

        return $result['session_token'];
    }

    /**
     * Pełny cert-based flow z embedded encryption key. Wymagane gdy
     * caller potrzebuje WYSŁAĆ faktury (Invoice/Send szyfruje payload
     * AES-256-CBC tym samym kluczem, który embedujemy tutaj w
     * `<EncryptionKey>` block podpisanego AuthTokenRequest).
     *
     * Per KSeF spec §3.2:
     *   1. Generujemy ephemeral AES-256-CBC key (32B random)
     *   2. Wrap'ujemy przez RSA-OAEP z MF public key
     *   3. Embedujemy base64 wrapped key w AuthTokenRequest XML
     *   4. Podpisujemy całość XAdES-BES tenant'a cert'em
     *   5. POST signed → MF zwraca sessionToken
     *   6. Caller trzyma {sessionToken, aesKey} razem do wysyłania faktur
     *
     * AES key NIE jest persistowany — zostaje w pamięci procesu /
     * w cache (KsefSessionManager dla cert flow będzie follow-up).
     *
     * @return array{session_token: string, aes_key: string}
     */
    public function authenticateWithEncryptionKey(Tenant $tenant): array
    {
        $aesKey = random_bytes(32);
        $wrappedAesKeyBase64 = $this->wrapAesKey($tenant, $aesKey);

        $result = $this->doAuthenticate($tenant, $wrappedAesKeyBase64);

        return [
            'session_token' => $result['session_token'],
            'aes_key' => $aesKey,
        ];
    }

    /**
     * Wewnętrzny helper z gołą logiką handshake'a. `$wrappedAesKeyBase64`
     * null = auth-only (sessionToken bez send capability), wartość =
     * pełen send-ready flow z embedded encryption key.
     *
     * @return array{session_token: string}
     */
    private function doAuthenticate(Tenant $tenant, ?string $wrappedAesKeyBase64): array
    {
        if (! $this->isReady($tenant)) {
            throw new \RuntimeException('Stajnia nie ma skonfigurowanego KSeF (cert + NIP).');
        }

        $host = $this->hostFor($tenant);

        // 1. GET challenge
        $challengeResp = Http::acceptJson()
            ->timeout(20)
            ->get($host.'/online/Session/AuthorisationChallenge', [
                'contextIdentifier' => [
                    'type' => 'onip',
                    'identifier' => $this->contextNip($tenant),
                ],
            ]);

        if (! $challengeResp->successful()) {
            throw new \RuntimeException('KSeF challenge failed: HTTP '.$challengeResp->status());
        }
        $challenge = (string) data_get($challengeResp->json(), 'challenge', '');
        if ($challenge === '') {
            throw new \RuntimeException('KSeF challenge empty.');
        }

        // 2. Build + sign AuthTokenRequest (opcjonalnie z EncryptionKey).
        $authXml = $this->signer->buildAuthTokenRequest(
            $challenge,
            $this->contextNip($tenant),
            (string) (data_get($tenant->settings, 'ksef.identifier_type') ?? 'certificateSubject'),
            $wrappedAesKeyBase64,
        );
        $signedXml = $this->signWith($tenant, $authXml);

        // 3. POST signed XML → session token
        $tokenResp = Http::withHeaders(['Content-Type' => 'application/octet-stream'])
            ->timeout(30)
            ->withBody($signedXml, 'application/octet-stream')
            ->post($host.'/online/Session/InitSigned');

        if (! $tokenResp->successful()) {
            Log::warning('KSeF auth failed', ['tenant' => $tenant->slug, 'status' => $tokenResp->status()]);
            throw new \RuntimeException('KSeF auth failed: HTTP '.$tokenResp->status());
        }

        $sessionToken = (string) data_get($tokenResp->json(), 'sessionToken.token', '');
        if ($sessionToken === '') {
            throw new \RuntimeException('KSeF session token missing in response.');
        }

        $this->audit->record('ksef.authenticated', 'Tenant', (string) $tenant->id, [
            'env' => (string) (data_get($tenant->settings, 'ksef.env') ?? 'test'),
            'with_encryption_key' => $wrappedAesKeyBase64 !== null,
        ]);

        return ['session_token' => $sessionToken];
    }

    /**
     * RSA-OAEP wrap of an AES-256 key with MF environment public key.
     * MF dostarcza klucze publiczne per environment (test/demo/prod) —
     * trzymamy je w `storage/app/ksef/public-key-{env}.pem` (te same
     * pliki, których używa transport `KsefHttpClient::getPublicKey`).
     *
     * @return string base64-encoded wrapped key (gotowy do XML).
     */
    private function wrapAesKey(Tenant $tenant, string $aesKey): string
    {
        $env = (string) (data_get($tenant->settings, 'ksef.env') ?? 'test');
        $disk = Storage::disk((string) config('services.ksef.public_key_disk', 'local'));
        $relativePath = 'ksef/public-key-'.$env.'.pem';

        $pem = null;
        if ($disk->exists($relativePath)) {
            $pem = (string) $disk->get($relativePath);
        }
        if ($pem === null || ! str_contains($pem, 'BEGIN PUBLIC KEY')) {
            // Fallback do konfiguracji env (services.ksef.public_key.{env}_pem)
            // — analogicznie do transport flow.
            $configured = (string) (config('services.ksef.public_key.'.$env.'_pem') ?? '');
            if ($configured !== '' && str_contains($configured, 'BEGIN PUBLIC KEY')) {
                $disk->put($relativePath, $configured);
                $pem = $configured;
            }
        }

        if ($pem === null) {
            throw new \RuntimeException(
                'KSeF MF public key brak — wgraj klucz publiczny MF '
                .'do storage/app/'.$relativePath
                .' lub ustaw KSEF_PUBLIC_KEY_'.strtoupper($env).'_PEM. '
                .'Klucz dostępny w https://www.gov.pl/web/kas/krajowy-system-e-faktur',
            );
        }

        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            throw new \RuntimeException('Invalid KSeF MF public key PEM (storage/app/'.$relativePath.').');
        }

        $wrapped = '';
        $ok = openssl_public_encrypt($aesKey, $wrapped, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
        if (! $ok) {
            throw new \RuntimeException('RSA-OAEP wrap of AES key failed (openssl_public_encrypt returned false).');
        }

        return base64_encode($wrapped);
    }

    /**
     * Podpisuje XML kluczem zapisanym w settings. Decyzja PFX vs PEM
     * zgodna z `cert_format`.
     */
    private function signWith(Tenant $tenant, string $xml): string
    {
        $cfg = (array) (data_get($tenant->settings, 'ksef') ?? []);
        $format = (string) ($cfg['cert_format'] ?? '');

        if ($format === 'pfx') {
            $pfxBytes = base64_decode((string) Crypt::decryptString((string) $cfg['cert_pfx_encrypted']));
            $password = (string) Crypt::decryptString((string) $cfg['cert_password_encrypted']);

            return $this->signer->signAuthTokenRequest($xml, $pfxBytes, $password, isPem: false);
        }

        if ($format === 'pem') {
            $crt = (string) Crypt::decryptString((string) $cfg['cert_crt_encrypted']);
            $key = (string) Crypt::decryptString((string) $cfg['cert_key_encrypted']);

            return $this->signer->signAuthTokenRequest($xml, $key, $crt, isPem: true);
        }

        throw new \RuntimeException('Brak skonfigurowanego certyfikatu KSeF.');
    }

    private function hostFor(Tenant $tenant): string
    {
        return match ((string) (data_get($tenant->settings, 'ksef.env') ?? 'test')) {
            'prod' => self::HOST_PROD,
            'demo' => self::HOST_DEMO,
            default => self::HOST_TEST,
        };
    }

    private function contextNip(Tenant $tenant): string
    {
        $nip = (string) (data_get($tenant->settings, 'ksef.context_nip') ?? '');

        return preg_replace('/[^0-9]/', '', $nip) ?? '';
    }
}
