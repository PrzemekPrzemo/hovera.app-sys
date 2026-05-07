<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     */
    public function authenticate(Tenant $tenant): string
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

        // 2. Build + sign AuthTokenRequest
        $authXml = $this->signer->buildAuthTokenRequest(
            $challenge,
            $this->contextNip($tenant),
            (string) (data_get($tenant->settings, 'ksef.identifier_type') ?? 'certificateSubject'),
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
        ]);

        return $sessionToken;
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
