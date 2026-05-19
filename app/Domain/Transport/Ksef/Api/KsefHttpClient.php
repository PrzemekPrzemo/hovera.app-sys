<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Low-level klient HTTP do KSeF API. Każdą operację API mapuje na
 * jeden publiczny method; warstwa wyżej (KsefSessionManager /
 * TransporterKsefService) komponuje je w business flow:
 *
 *    handshake = challenge → encrypt token → init token
 *    submit    = AES-encrypt XML → send → reference
 *    poll      = status by reference → processingCode mapping
 *
 * Endpoint hosts (zgodne z dokumentacją MF — Krajowy System e-Faktur):
 *   - test:       https://ksef-test.mf.gov.pl/api
 *   - demo:       https://ksef-demo.mf.gov.pl/api
 *   - production: https://ksef.mf.gov.pl/api
 *
 * Public key MF:
 *   KSeF dostarcza klucz publiczny RSA do wrapowania AES-256 klucza
 *   sesyjnego. Klucz różni się per środowisko i jest publicznie
 *   dostępny w dokumentacji. Tutaj cachujemy w storage/app/ksef/
 *   `public-key-{env}.pem`. Jeśli plik nie istnieje, próbujemy
 *   pobrać z `config('services.ksef.public_key_url.{env}')`; jeśli
 *   to też nie jest skonfigurowane, rzucamy czytelny błąd.
 *
 * NOTE: Endpoint URL klucza publicznego MF nie jest dobrze
 * udokumentowany jako stabilny REST endpoint — w praktyce klucz się
 * dystrybuuje przez dokumentację i zmienia raz na rotację. Dlatego
 * preferowany flow to ręczne wgranie pliku do `storage/app/ksef/`.
 */
class KsefHttpClient
{
    public const HOST_TEST = 'https://ksef-test.mf.gov.pl/api';

    public const HOST_DEMO = 'https://ksef-demo.mf.gov.pl/api';

    public const HOST_PROD = 'https://ksef.mf.gov.pl/api';

    public const ENV_TEST = 'test';

    public const ENV_DEMO = 'demo';

    public const ENV_PROD = 'production';

    public function __construct(
        private readonly int $timeout = 30,
    ) {}

    /**
     * Krok 1 handshake: pobranie challenge'u od MF.
     *
     *   POST /online/Session/AuthorisationChallenge
     *   body: {"contextIdentifier": {"type": "onip", "identifier": "<NIP>"}}
     *   → 200 {"challenge": "<...>", "timestamp": "2026-05-19T...Z"}
     *
     * @return array{challenge: string, timestamp: string}
     */
    public function getAuthorizationChallenge(string $environment, string $nip): array
    {
        $host = $this->hostFor($environment);
        $normalizedNip = $this->normalizeNip($nip);

        $response = $this->baseRequest()
            ->acceptJson()
            ->asJson()
            ->post($host.'/online/Session/AuthorisationChallenge', [
                'contextIdentifier' => [
                    'type' => 'onip',
                    'identifier' => $normalizedNip,
                ],
            ]);

        if (! $response->successful()) {
            throw new KsefApiException(
                'AuthorisationChallenge failed: HTTP '.$response->status(),
                $response->status(),
                $this->safeBody($response),
            );
        }

        $challenge = (string) ($response->json('challenge') ?? '');
        $timestamp = (string) ($response->json('timestamp') ?? '');

        if ($challenge === '' || $timestamp === '') {
            throw new KsefApiException(
                'AuthorisationChallenge response missing challenge/timestamp',
                $response->status(),
                $this->safeBody($response),
            );
        }

        return ['challenge' => $challenge, 'timestamp' => $timestamp];
    }

    /**
     * Krok 2 handshake: token-based InitToken.
     *
     * Buduje payload zgodny ze schematem MF InitSessionTokenRequest:
     *   1. Wrap AES-256 klucz przez RSA-OAEP z klucz publicznym MF
     *   2. Zaszyfrować `token + challenge_unix_ms` przez AES-256-CBC
     *   3. POST XML do /online/Session/InitToken
     *
     *   → 200 {"sessionToken": {"token": "<...>", "expirationDate": "..."}}
     *
     * Zwraca: session token + AES klucz (oba potrzebne — token do
     * nagłówka SessionToken, AES klucz do szyfrowania payloadu faktur).
     *
     * @return array{session_token: string, aes_key: string, expires_at: Carbon}
     */
    public function initSessionTokenWithToken(
        string $environment,
        string $nip,
        string $authToken,
        string $challenge,
        string $timestamp,
        ?string $publicKeyPem = null,
    ): array {
        $host = $this->hostFor($environment);
        $pem = $publicKeyPem ?? $this->getPublicKey($environment);

        // 1. Wygeneruj świeży AES-256 klucz (32 bytes) + IV (16 bytes).
        $aesKey = random_bytes(32);
        $iv = random_bytes(16);

        // 2. Wrap AES klucz przez RSA-OAEP (klucz publiczny MF).
        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            throw new KsefApiException(
                'KSeF public key PEM is invalid — sprawdź storage/app/ksef/public-key-'.$environment.'.pem',
                500,
                [],
            );
        }
        $wrappedAesKey = '';
        $ok = openssl_public_encrypt($aesKey, $wrappedAesKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
        if (! $ok) {
            throw new KsefApiException('RSA-OAEP wrap of AES key failed.', 500, []);
        }

        // 3. Zaszyfruj plaintext: "<token>|<challenge_unix_ms>" przez AES-256-CBC.
        // KSeF dokumentuje że plaintext to dokładnie ten format: token
        // konkatenowany z timestampem challenge'u (millisekundy unix
        // z odpowiedzi /AuthorisationChallenge).
        $challengeMillis = (int) (strtotime($timestamp) * 1000);
        $plaintext = $authToken.'|'.$challengeMillis;
        $encryptedToken = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
        );
        if ($encryptedToken === false) {
            throw new KsefApiException('AES-256-CBC encrypt of token+timestamp failed.', 500, []);
        }

        // 4. Buduj XML InitSessionTokenRequest. Schema MF (uproszczona,
        // wystarczająca dla token-based init flow). Klucz AES + szyfrowany
        // token są przekazywane jako base64.
        $xml = $this->buildInitSessionTokenXml(
            challenge: $challenge,
            nip: $this->normalizeNip($nip),
            encryptedTokenBase64: base64_encode($iv.$encryptedToken),
            wrappedAesKeyBase64: base64_encode($wrappedAesKey),
        );

        // 5. POST do MF.
        $response = $this->baseRequest()
            ->withBody($xml, 'application/octet-stream')
            ->acceptJson()
            ->post($host.'/online/Session/InitToken');

        if (! $response->successful()) {
            throw new KsefApiException(
                'InitToken failed: HTTP '.$response->status(),
                $response->status(),
                $this->safeBody($response),
            );
        }

        $sessionToken = (string) ($response->json('sessionToken.token') ?? '');
        if ($sessionToken === '') {
            throw new KsefApiException(
                'InitToken response missing sessionToken.token',
                $response->status(),
                $this->safeBody($response),
            );
        }

        $expirationRaw = (string) ($response->json('sessionToken.expirationDate') ?? '');
        $expiresAt = $expirationRaw !== ''
            ? Carbon::parse($expirationRaw)
            // KSeF default: token żyje ~2h; konserwatywnie 1h jeśli MF
            // nie podał — UX-wise to lepiej re-handshake'ować częściej
            // niż dostać HTTP 401 w środku batcha.
            : now()->addHour();

        return [
            'session_token' => $sessionToken,
            'aes_key' => $aesKey,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Wysłanie faktury (FA(2/3) XML) jako szyfrowany payload AES-256-CBC.
     *
     *   POST /online/Invoice/Send
     *   headers: SessionToken: <token>
     *   body: <iv><ciphertext> binary
     *
     * @return array{element_reference_number: string}
     */
    public function sendInvoice(
        string $environment,
        string $sessionToken,
        string $aesKey,
        string $invoiceXml,
    ): array {
        $host = $this->hostFor($environment);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $invoiceXml,
            'aes-256-cbc',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
        );
        if ($ciphertext === false) {
            throw new KsefApiException('AES encrypt invoice payload failed.', 500, []);
        }

        $response = $this->baseRequest()
            ->withHeaders([
                'SessionToken' => $sessionToken,
                'Content-Type' => 'application/octet-stream',
            ])
            ->acceptJson()
            ->withBody($iv.$ciphertext, 'application/octet-stream')
            ->post($host.'/online/Invoice/Send');

        if (! $response->successful()) {
            throw new KsefApiException(
                'SendInvoice failed: HTTP '.$response->status(),
                $response->status(),
                $this->safeBody($response),
            );
        }

        $reference = (string) ($response->json('elementReferenceNumber')
            ?? $response->json('referenceNumber')
            ?? '');

        if ($reference === '') {
            throw new KsefApiException(
                'SendInvoice response missing elementReferenceNumber',
                $response->status(),
                $this->safeBody($response),
            );
        }

        return ['element_reference_number' => $reference];
    }

    /**
     * Sprawdzenie statusu wcześniej wysłanej faktury.
     *
     *   GET /online/Invoice/Status/{reference}
     *   headers: SessionToken: <token>
     *   → {"processingCode": "200", "processingDescription": "...", "ksefReferenceNumber": "..."}
     *
     * @return array{processing_code: string, processing_description: string, ksef_reference_number: ?string, raw_body: array<string,mixed>, http_status: int}
     */
    public function getInvoiceStatus(
        string $environment,
        string $sessionToken,
        string $invoiceElementReference,
    ): array {
        $host = $this->hostFor($environment);
        $response = $this->baseRequest()
            ->withHeaders(['SessionToken' => $sessionToken])
            ->acceptJson()
            ->get($host.'/online/Invoice/Status/'.rawurlencode($invoiceElementReference));

        $body = $response->json();
        if (! is_array($body)) {
            $body = ['raw' => (string) $response->body()];
        }

        return [
            'processing_code' => (string) ($body['processingCode'] ?? ''),
            'processing_description' => (string) ($body['processingDescription'] ?? ''),
            'ksef_reference_number' => isset($body['ksefReferenceNumber']) ? (string) $body['ksefReferenceNumber'] : null,
            'raw_body' => $body,
            'http_status' => $response->status(),
        ];
    }

    /**
     * Zamknięcie sesji KSeF. Best-effort — błąd nie blokuje aplikacji.
     */
    public function closeSession(string $environment, string $sessionToken): void
    {
        try {
            $this->baseRequest()
                ->withHeaders(['SessionToken' => $sessionToken])
                ->post($this->hostFor($environment).'/online/Session/Terminate');
        } catch (\Throwable $e) {
            Log::info('ksef.session.terminate_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Pobiera (cached) klucz publiczny RSA MF dla danego środowiska.
     *
     * Strategia:
     *   1. Cache file: storage/app/ksef/public-key-{env}.pem
     *   2. Jeśli brak, config('services.ksef.public_key.{env}_pem')
     *      (inline PEM w env) → zapisz do cache i zwróć.
     *   3. Jeśli też brak, rzucamy z czytelnym komunikatem.
     *
     * NIE pobieramy z HTTP — MF nie publikuje stabilnego REST endpointa
     * na klucz, dystrybucja idzie przez dokumentację. To zabezpiecza
     * przed podmianą klucza przez man-in-the-middle (musi go ops wgrać).
     */
    public function getPublicKey(string $environment): string
    {
        $env = $this->normalizeEnvironment($environment);
        $disk = Storage::disk(config('services.ksef.public_key_disk', 'local'));
        $relativePath = 'ksef/public-key-'.$env.'.pem';

        if ($disk->exists($relativePath)) {
            $pem = (string) $disk->get($relativePath);
            if ($this->looksLikePem($pem)) {
                return $pem;
            }
        }

        $configured = (string) (config('services.ksef.public_key.'.$env.'_pem') ?? '');
        if ($configured !== '' && $this->looksLikePem($configured)) {
            $disk->put($relativePath, $configured);

            return $configured;
        }

        throw new RuntimeException(
            'KSeF public key for environment "'.$env.'" is not configured. '
            .'Wgraj klucz publiczny MF do storage/app/ksef/public-key-'.$env.'.pem '
            .'lub ustaw KSEF_PUBLIC_KEY_'.strtoupper($env).'_PEM. '
            .'Klucz dostępny w dokumentacji MF: https://www.gov.pl/web/kas/krajowy-system-e-faktur'
        );
    }

    private function buildInitSessionTokenXml(
        string $challenge,
        string $nip,
        string $encryptedTokenBase64,
        string $wrappedAesKeyBase64,
    ): string {
        // Uproszczony XML — schema MF InitSessionTokenRequest, wariant
        // token-based (nie cert-based). Pełen XSD jest publikowany przez
        // MF; tutaj generujemy minimum wymagane przez endpoint.
        $ns = 'http://ksef.mf.gov.pl/schema/gtw/svc/online/auth/request/2021/10/01/0001';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<ns:InitSessionTokenRequest xmlns:ns="'.$ns.'">'
            .'<ns:Context>'
            .'<ns:Challenge>'.htmlspecialchars($challenge, ENT_XML1).'</ns:Challenge>'
            .'<ns:Identifier xsi:type="ns:SubjectIdentifierByCompanyType" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<ns:Identifier>'.htmlspecialchars($nip, ENT_XML1).'</ns:Identifier>'
            .'</ns:Identifier>'
            .'<ns:DocumentType>'
            .'<ns:Service>KSeF</ns:Service>'
            .'<ns:FormCode>'
            .'<ns:SystemCode>FA (2)</ns:SystemCode>'
            .'<ns:SchemaVersion>1-0E</ns:SchemaVersion>'
            .'<ns:TargetNamespace>http://crd.gov.pl/wzor/2023/06/29/12648/</ns:TargetNamespace>'
            .'<ns:Value>FA</ns:Value>'
            .'</ns:FormCode>'
            .'</ns:DocumentType>'
            .'<ns:Token>'.$encryptedTokenBase64.'</ns:Token>'
            .'</ns:Context>'
            .'<ns:DocumentEncryption>'
            .'<ns:EncryptionKey>'.$wrappedAesKeyBase64.'</ns:EncryptionKey>'
            .'<ns:EncryptionKeyCharacterCode>UTF-8</ns:EncryptionKeyCharacterCode>'
            .'</ns:DocumentEncryption>'
            .'</ns:InitSessionTokenRequest>';
    }

    public function hostFor(string $environment): string
    {
        return match ($this->normalizeEnvironment($environment)) {
            self::ENV_PROD => self::HOST_PROD,
            self::ENV_DEMO => self::HOST_DEMO,
            default => self::HOST_TEST,
        };
    }

    private function normalizeEnvironment(string $env): string
    {
        $lower = strtolower(trim($env));

        return match ($lower) {
            'prod', 'production' => self::ENV_PROD,
            'demo' => self::ENV_DEMO,
            default => self::ENV_TEST,
        };
    }

    private function normalizeNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip) ?? '';
    }

    private function baseRequest(): PendingRequest
    {
        return Http::timeout($this->timeout)->connectTimeout(10);
    }

    /**
     * @return array<string,mixed>
     */
    private function safeBody(Response $response): array
    {
        $body = (string) $response->body();
        if (strlen($body) > 16 * 1024) {
            $body = substr($body, 0, 16 * 1024).'…[truncated]';
        }

        return [
            'status' => $response->status(),
            'body' => $body,
            'received_at' => now()->toIso8601String(),
        ];
    }

    private function looksLikePem(string $candidate): bool
    {
        return str_contains($candidate, '-----BEGIN PUBLIC KEY-----')
            || str_contains($candidate, '-----BEGIN RSA PUBLIC KEY-----')
            || str_contains($candidate, '-----BEGIN CERTIFICATE-----');
    }
}
