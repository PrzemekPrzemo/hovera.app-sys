<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef;

use App\Domain\Transport\Ksef\Api\KsefApiException;
use App\Domain\Transport\Ksef\Api\KsefHttpClient;
use App\Domain\Transport\Ksef\Session\KsefSessionManager;
use App\Enums\TenantType;
use App\Enums\TransportKsefStatus;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\TransportSettings;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-transporter KSeF — passthrough warstwa dla `TransportInvoice`.
 *
 * Hovera NIE jest wystawcą faktur transportowych (patrz docs/TRANSPORT.md
 * §12 — marketplace positioning). Każdy transporter wprowadza WŁASNY
 * token autoryzacyjny KSeF, WŁASNY NIP, i wybiera środowisko (test/prod).
 *
 * Ten serwis wykonuje pełny handshake KSeF:
 *   1. AuthorisationChallenge — pobranie challenge'u od MF
 *   2. InitSessionToken (token-based) — wrap AES-256 klucza przez
 *      RSA-OAEP z kluczem publicznym MF, zaszyfrowanie {token+timestamp}
 *      AES-256-CBC, POST XML payload → MF zwraca SessionToken
 *   3. SessionToken (TTL ~2h) trafia do cachu (KsefSessionManager)
 *   4. /Invoice/Send z payloadem szyfrowanym AES-256-CBC tym samym
 *      kluczem co handshake
 *   5. /Invoice/Status (poll) — async ProcessingCode mapping
 *
 * Token autoryzacyjny NIGDY nie pojawia się w wyjątkach ani logach.
 * Patrz `TransportSettings::redactedTokenPreview()` dla bezpiecznego
 * podglądu w ops debug.
 *
 * Co NIE jest jeszcze obsługiwane:
 *   - faktury korygujące (KOR) — XML budowniczy ma case ale flow
 *     korekt wymaga referencji do oryginalnej FV w KSeF
 *   - XSD walidacja przed wysyłką (MF i tak waliduje serwerowo)
 *   - batch endpoint /Invoice/Batch (na razie wysyłamy pojedynczo)
 */
class TransporterKsefService
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly TenantAuditLogger $audit,
        private readonly KsefHttpClient $client,
        private readonly KsefSessionManager $sessions,
    ) {}

    /**
     * Czy aktywny tenant ma KOMPLETNĄ konfigurację (token + NIP +
     * weryfikację konta), gotową do wywołań KSeF.
     */
    public function isEnabledForCurrentTransporter(): bool
    {
        try {
            $this->assertConfigured();

            return true;
        } catch (KsefNotConfiguredException) {
            return false;
        }
    }

    /**
     * Wyślij FV do KSeF w imieniu transportera. Buduje XML, wykonuje
     * pełny handshake (lub używa cached session token), zaszyfrowane
     * AES wysyła do /Invoice/Send, zapisuje stan na FV.
     *
     * Nigdy nie rzuca wyjątkiem zawierającym token — błędy HTTP są
     * mapowane na `KsefSubmissionResult::error()` lub `::rejected()`.
     *
     * @throws KsefNotConfiguredException gdy brakuje credentials
     */
    public function submit(TransportInvoice $invoice): KsefSubmissionResult
    {
        $settings = $this->assertConfigured();
        $xml = $this->generateXml($invoice);

        // Cache XML zanim wykonamy POST — jeśli MF padnie / timeout,
        // mamy ślad co próbowaliśmy wysłać (do retry / debug).
        $invoice->forceFill(['ksef_xml' => $xml])->save();

        try {
            $session = $this->sessions->getActiveSession(
                tenant: $this->tenants->tenantOrFail(),
                environment: $this->environment($settings),
                authToken: (string) $settings->getKsefToken(),
                nip: $this->resolveNip($settings),
            );
        } catch (KsefApiException $e) {
            $this->logError($settings, 'handshake_failed', [
                'message' => $e->getMessage(),
                'http_status' => $e->httpStatus,
            ]);

            return $this->persistError(
                $invoice,
                $settings,
                'KSeF handshake failed: '.$e->getMessage(),
                $e->payload,
                isReject: $e->httpStatus >= 400 && $e->httpStatus < 500,
            );
        } catch (Throwable $e) {
            return $this->handleException($invoice, $settings, $e);
        }

        try {
            $result = $this->client->sendInvoice(
                environment: $this->environment($settings),
                sessionToken: $session['token'],
                aesKey: $session['aes_key'],
                invoiceXml: $xml,
            );
        } catch (KsefApiException $e) {
            // 401 = session token revoked / expired wcześniej niż MF
            // podał. Spróbujmy raz force-refresh i ponów.
            if ($e->httpStatus === 401) {
                try {
                    $session = $this->sessions->forceRefresh(
                        tenant: $this->tenants->tenantOrFail(),
                        environment: $this->environment($settings),
                        authToken: (string) $settings->getKsefToken(),
                        nip: $this->resolveNip($settings),
                    );
                    $result = $this->client->sendInvoice(
                        environment: $this->environment($settings),
                        sessionToken: $session['token'],
                        aesKey: $session['aes_key'],
                        invoiceXml: $xml,
                    );
                } catch (Throwable $retryError) {
                    return $this->persistError(
                        $invoice,
                        $settings,
                        'KSeF send failed after re-handshake: '.$retryError->getMessage(),
                        $e->payload,
                        isReject: false,
                    );
                }
            } else {
                return $this->persistError(
                    $invoice,
                    $settings,
                    'KSeF send failed: '.$e->getMessage(),
                    $e->payload,
                    isReject: $e->httpStatus >= 400 && $e->httpStatus < 500,
                );
            }
        } catch (Throwable $e) {
            return $this->handleException($invoice, $settings, $e);
        }

        $reference = $result['element_reference_number'];

        $invoice->forceFill([
            'ksef_status' => TransportKsefStatus::Submitted,
            'ksef_reference_number' => $reference,
            'ksef_reference' => $reference, // legacy compat
            'ksef_submitted_at' => now(),
            'ksef_sent_at' => now(),
            'ksef_error_payload' => null,
        ])->save();

        $this->audit->record('transport_invoice.ksef_submitted', 'TransportInvoice', (string) $invoice->id, [
            'number' => $invoice->number,
            'reference_number' => $reference,
            'env' => $this->environment($settings),
            'token_preview' => $settings->redactedTokenPreview(),
        ]);

        return KsefSubmissionResult::submitted($reference);
    }

    /**
     * Sprawdź status wcześniejszego submit. Używane przez UI ("Odśwież
     * status") oraz przez cron `transport:ksef:poll-submitted`.
     *
     * @throws KsefNotConfiguredException
     */
    public function refreshStatus(TransportInvoice $invoice): KsefStatusResult
    {
        $settings = $this->assertConfigured();

        $reference = (string) ($invoice->ksef_reference_number ?? $invoice->ksef_reference ?? '');
        if ($reference === '') {
            return KsefStatusResult::error('No reference number on invoice', []);
        }

        try {
            $session = $this->sessions->getActiveSession(
                tenant: $this->tenants->tenantOrFail(),
                environment: $this->environment($settings),
                authToken: (string) $settings->getKsefToken(),
                nip: $this->resolveNip($settings),
            );
        } catch (Throwable $e) {
            $this->logError($settings, 'refresh_status_handshake_failed', ['error' => $e->getMessage()]);

            return KsefStatusResult::error('KSeF status handshake failed: '.$e->getMessage(), []);
        }

        try {
            $status = $this->client->getInvoiceStatus(
                environment: $this->environment($settings),
                sessionToken: $session['token'],
                invoiceElementReference: $reference,
            );
        } catch (KsefApiException $e) {
            $invoice->forceFill([
                'ksef_status' => TransportKsefStatus::Error,
                'ksef_error_payload' => $e->payload,
            ])->save();

            return KsefStatusResult::error('KSeF HTTP '.$e->httpStatus, $e->payload);
        } catch (Throwable $e) {
            $this->logError($settings, 'refresh_status_http_exception', ['error' => $e->getMessage()]);

            return KsefStatusResult::error('KSeF status call failed: '.$e->getMessage(), []);
        }

        return $this->mapStatusResponse($invoice, $reference, $status);
    }

    /**
     * Buduje XML FA dla transport FV. Builder identyczny strukturalnie
     * jak CentralKsefService, ale z `<SystemInfo>Hovera Transport
     * Passthrough</SystemInfo>` — tak by audyt MF mógł rozpoznać, że
     * Hovera jest tylko software'em, nie wystawcą.
     */
    public function generateXml(TransportInvoice $invoice): string
    {
        $settings = $this->settings();
        $sellerNip = $this->normalizeNip((string) ($settings?->ksef_nip ?? $invoice->seller_nip ?? ''));
        $sellerName = (string) ($invoice->seller_name ?? '');
        $buyerNip = $this->normalizeNip((string) ($invoice->buyer_nip ?? ''));
        $buyerName = (string) ($invoice->buyer_name ?? 'Klient');
        $issued = $invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d');
        $net = number_format(((int) $invoice->subtotal_cents) / 100, 2, '.', '');
        $vat = number_format(((int) $invoice->vat_cents) / 100, 2, '.', '');
        $total = number_format(((int) $invoice->total_cents) / 100, 2, '.', '');

        $kodSystemowy = match ((string) ($invoice->kind?->value ?? $invoice->kind ?? 'fv')) {
            'fv_korekta' => 'KOR',
            'fv_proforma' => 'PRO',
            default => 'FA',
        };

        // FA(3) wymaga reference do oryginalnej FV w `<DaneFaKorygowanej>`
        // gdy `RodzajFaktury=KOR`. Bez tego XML waliduje się serwerowo
        // przez MF z błędem „missing reference". Patrz docs/TRANSPORT.md §16.
        $correctionRefXml = '';
        if ($kodSystemowy === 'KOR' && $invoice->corrects_invoice_id) {
            $invoice->loadMissing('correctsInvoice');
            $original = $invoice->correctsInvoice;
            if ($original !== null) {
                $origNumber = htmlspecialchars((string) $original->number, ENT_XML1);
                $origIssued = $original->issued_at?->format('Y-m-d') ?? $issued;
                $correctionRefXml = '<DaneFaKorygowanej>'
                    .'<NrFaKorygowanej>'.$origNumber.'</NrFaKorygowanej>'
                    .'<DataWystFaKorygowanej>'.$origIssued.'</DataWystFaKorygowanej>'
                    .'</DaneFaKorygowanej>';
            }
        }

        $ns = 'http://crd.gov.pl/wzor/2023/06/29/12648/';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Faktura xmlns="'.$ns.'">'
            .'<Naglowek>'
            .'<KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>'
            .'<WariantFormularza>3</WariantFormularza>'
            .'<DataWytworzeniaFa>'.gmdate('Y-m-d\TH:i:s\Z').'</DataWytworzeniaFa>'
            .'<SystemInfo>Hovera Transport Passthrough</SystemInfo>'
            .'</Naglowek>'
            .'<Podmiot1>'
            .'<DaneIdentyfikacyjne>'
            .'<NIP>'.htmlspecialchars($sellerNip, ENT_XML1).'</NIP>'
            .'<Nazwa>'.htmlspecialchars($sellerName, ENT_XML1).'</Nazwa>'
            .'</DaneIdentyfikacyjne>'
            .'</Podmiot1>'
            .'<Podmiot2>'
            .'<DaneIdentyfikacyjne>'
            .($buyerNip !== '' ? '<NIP>'.htmlspecialchars($buyerNip, ENT_XML1).'</NIP>' : '<BrakID>1</BrakID>')
            .'<Nazwa>'.htmlspecialchars($buyerName, ENT_XML1).'</Nazwa>'
            .'</DaneIdentyfikacyjne>'
            .'</Podmiot2>'
            .'<Fa>'
            .'<KodWaluty>'.htmlspecialchars((string) ($invoice->currency ?? 'PLN'), ENT_XML1).'</KodWaluty>'
            .'<P_1>'.$issued.'</P_1>'
            .'<P_2>'.htmlspecialchars((string) $invoice->number, ENT_XML1).'</P_2>'
            .'<P_13_1>'.$net.'</P_13_1>'
            .'<P_14_1>'.$vat.'</P_14_1>'
            .'<P_15>'.$total.'</P_15>'
            .'<RodzajFaktury>'.$kodSystemowy.'</RodzajFaktury>'
            .$correctionRefXml
            .'</Fa>'
            .'</Faktura>';
    }

    /**
     * Pełen test handshake — pobiera challenge i InitToken, sprawdza
     * czy MF akceptuje token transportera (faktyczna walidacja, nie
     * tylko ping endpoint). Zwraca [success, message]; NIGDY nie
     * ujawnia tokenu.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $settings = $this->assertConfigured();
        } catch (KsefNotConfiguredException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        try {
            // Force-refresh — chcemy faktycznie sprawdzić, czy token
            // wykonuje pełen handshake. Cache'owanie tu byłoby fałszywe
            // bo cached session != "token jest poprawny".
            $this->sessions->forceRefresh(
                tenant: $this->tenants->tenantOrFail(),
                environment: $this->environment($settings),
                authToken: (string) $settings->getKsefToken(),
                nip: $this->resolveNip($settings),
            );
        } catch (KsefApiException $e) {
            return [
                'success' => false,
                'message' => 'Token nie został zaakceptowany przez KSeF: '
                    .$this->cleanErrorMessage($e->getMessage()),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'KSeF unreachable: '.$this->cleanErrorMessage($e->getMessage()),
            ];
        }

        return [
            'success' => true,
            'message' => 'KSeF '.$this->environment($settings).' OK — handshake powiódł się.',
        ];
    }

    /**
     * Asercja konfiguracji aktywnego tenanta. Zwraca TransportSettings
     * gotowe do użycia.
     *
     * @throws KsefNotConfiguredException
     */
    private function assertConfigured(): TransportSettings
    {
        $tenant = $this->tenants->current();
        if ($tenant !== null && method_exists($tenant, 'isVerifiedTransporter')) {
            $type = $tenant->type;
            $isTransporterType = $type instanceof TenantType
                ? $type === TenantType::Transporter
                : (string) $type === 'transporter';

            if ($isTransporterType && ! $tenant->isVerifiedTransporter()) {
                throw KsefNotConfiguredException::tenantNotVerified();
            }
        }

        $settings = $this->settings();
        if ($settings === null) {
            throw KsefNotConfiguredException::missingToken();
        }

        if (! (bool) $settings->ksef_enabled) {
            throw KsefNotConfiguredException::notEnabled();
        }

        if ($settings->getKsefToken() === null) {
            throw KsefNotConfiguredException::missingToken();
        }

        if (empty($settings->ksef_nip) && empty($tenant?->tax_id)) {
            throw KsefNotConfiguredException::missingNip();
        }

        return $settings;
    }

    private function settings(): ?TransportSettings
    {
        if (! $this->tenants->hasTenant()) {
            return null;
        }

        return TransportSettings::current();
    }

    private function environment(TransportSettings $settings): string
    {
        return match (strtolower((string) $settings->ksef_environment)) {
            'prod', 'production' => KsefHttpClient::ENV_PROD,
            'demo' => KsefHttpClient::ENV_DEMO,
            default => KsefHttpClient::ENV_TEST,
        };
    }

    private function resolveNip(TransportSettings $settings): string
    {
        $nip = (string) ($settings->ksef_nip ?? $this->tenants->current()?->tax_id ?? '');

        return $this->normalizeNip($nip);
    }

    /**
     * Mapuje odpowiedź KSeF /Invoice/Status na lokalny enum status.
     *
     * @param  array{processing_code: string, processing_description: string, ksef_reference_number: ?string, raw_body: array<string,mixed>, http_status: int}  $status
     */
    private function mapStatusResponse(TransportInvoice $invoice, string $reference, array $status): KsefStatusResult
    {
        $code = $status['processing_code'];
        $desc = $status['processing_description'];
        $payload = [
            'status' => $status['http_status'],
            'body' => $status['raw_body'],
            'received_at' => now()->toIso8601String(),
        ];

        // Mapowanie ProcessingCode MF (zgodnie z dokumentacją API):
        //   100 = waiting for processing
        //   110 = in progress
        //   200 = accepted (z numerem KSeF)
        //   3xx = pending nadal
        //   4xx = błąd merytoryczny (zły NIP, brak P_15, etc.) → rejected
        //   5xx = błąd techniczny po stronie MF → error
        if ($code === '200') {
            $invoice->forceFill([
                'ksef_status' => TransportKsefStatus::Accepted,
                'ksef_accepted_at' => now(),
                'ksef_reference_number' => $status['ksef_reference_number'] ?? $reference,
                'ksef_error_payload' => null,
            ])->save();

            $this->audit->record('transport_invoice.ksef_accepted', 'TransportInvoice', (string) $invoice->id, [
                'number' => $invoice->number,
                'reference_number' => $reference,
            ]);

            return KsefStatusResult::accepted($reference);
        }

        if (in_array($code, ['100', '110', '300', '305', '315'], true)) {
            return KsefStatusResult::pending($reference);
        }

        $intCode = (int) $code;
        if ($intCode >= 500 && $intCode < 600) {
            $invoice->forceFill([
                'ksef_status' => TransportKsefStatus::Error,
                'ksef_error_payload' => $payload,
            ])->save();

            return KsefStatusResult::error('KSeF processing error '.$code, $payload);
        }

        // 4xx / inne — rejection.
        $invoice->forceFill([
            'ksef_status' => TransportKsefStatus::Rejected,
            'ksef_error_payload' => $payload,
        ])->save();

        $this->audit->record('transport_invoice.ksef_rejected', 'TransportInvoice', (string) $invoice->id, [
            'number' => $invoice->number,
            'reference_number' => $reference,
            'processing_code' => $code,
        ]);

        return KsefStatusResult::rejected($desc !== '' ? $desc : 'KSeF rejected', $payload, $reference);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function persistError(
        TransportInvoice $invoice,
        TransportSettings $settings,
        string $message,
        array $payload,
        bool $isReject = false,
    ): KsefSubmissionResult {
        $status = $isReject ? TransportKsefStatus::Rejected : TransportKsefStatus::Error;

        $invoice->forceFill([
            'ksef_status' => $status,
            'ksef_error_payload' => $payload,
        ])->save();

        $this->logError($settings, 'submit_failed', [
            'invoice_id' => $invoice->id,
            'number' => $invoice->number,
            'message' => $message,
            'http_status' => $payload['status'] ?? null,
        ]);

        $this->audit->record(
            $isReject ? 'transport_invoice.ksef_rejected' : 'transport_invoice.ksef_error',
            'TransportInvoice',
            (string) $invoice->id,
            [
                'number' => $invoice->number,
                'message' => $message,
                'token_preview' => $settings->redactedTokenPreview(),
            ],
        );

        return $isReject
            ? KsefSubmissionResult::rejected($message, $payload)
            : KsefSubmissionResult::error($message, $payload);
    }

    private function handleException(
        TransportInvoice $invoice,
        TransportSettings $settings,
        Throwable $e,
    ): KsefSubmissionResult {
        // Scrub: defensive — nigdy nie propagujemy raw Throwable, tylko
        // getMessage(), które nie powinno zawierać tokenu (KsefHttpClient
        // i KsefApiException ich nie wstawiają).
        $message = 'KSeF call failed: '.$this->cleanErrorMessage($e->getMessage());

        return $this->persistError($invoice, $settings, $message, [
            'exception' => get_class($e),
            'message' => $this->cleanErrorMessage($e->getMessage()),
            'received_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logError(TransportSettings $settings, string $event, array $context): void
    {
        Log::warning('ksef.transporter.'.$event, array_merge($context, [
            'env' => (string) $settings->ksef_environment,
            'token_preview' => $settings->redactedTokenPreview(),
        ]));
    }

    private function normalizeNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip) ?? '';
    }

    /**
     * Defensywne wyciągnięcie potencjalnych sekretów z error message.
     * Czyste fragmenty zostawiamy; gdyby user wkleił coś dziwnego w
     * token, scrubujemy długie alfanumeryczne ciągi (>20 znaków).
     */
    private function cleanErrorMessage(string $message): string
    {
        return (string) preg_replace('/[A-Za-z0-9+\/]{20,}/', '[REDACTED]', $message);
    }
}
