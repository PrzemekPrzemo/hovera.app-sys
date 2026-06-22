<?php

declare(strict_types=1);

namespace App\Services\Ksef;

use App\Domain\Transport\Ksef\Api\KsefApiException;
use App\Domain\Transport\Ksef\Api\KsefHttpClient;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-tenant submit/poll dla regular `Invoice` (boarding/lekcje/pasze stajni).
 *
 * Mirror `App\Domain\Transport\Ksef\TransporterKsefService` ale dla cert-based
 * auth flow (zamiast token-based) i regular Invoice (zamiast TransportInvoice).
 *
 * Flow:
 *   1. `KsefClient::authenticateWithEncryptionKey` — handshake, dostajemy
 *      sessionToken + AES-256 ephemeral key
 *   2. `KsefInvoiceXmlBuilder::build` — FA(3) XML faktury
 *   3. `KsefHttpClient::sendInvoice` — POST /online/Invoice/Send z AES-256-CBC
 *      encrypted body
 *   4. MF zwraca `elementReferenceNumber` — to identyfikator do późniejszych
 *      query po /Invoice/Status
 *   5. Zapis na invoices: ksef_status='submitted', ksef_reference_number,
 *      ksef_submitted_at, ksef_environment, ksef_xml (cache do retry)
 *
 * AES key NIE jest persistowany — generuje się fresh per submit. To trochę
 * marnotrawne (cache session = 1 handshake na batch faktur), ale bezpieczne
 * — krypto nigdy nie wycieka poza process memory. Cache trafi w follow-up
 * z mirror'em `KsefSessionManager` z transport flow.
 *
 * Status lifecycle (stored jako string w `Invoice::ksef_status`):
 *   - null            → never submitted
 *   - 'submitted'     → MF accepted submit, awaiting async processing
 *   - 'accepted'      → MF accepted invoice (polled later via refreshStatus)
 *   - 'rejected'      → MF rejected on async processing
 *   - 'error'         → technical error (network, MF 5xx, etc.)
 *
 * Każdy soft fail łapany — error_payload na invoice + Log + zwracamy
 * `TenantKsefSubmissionResult::error/rejected`. Nie rzucamy do callera
 * żeby ten obsługiwał błąd reżimowo (UI Notification, retry queue itd.)
 * a nie 500 do user'a.
 */
class TenantKsefSubmissionService
{
    public const STATUS_NOT_SUBMITTED = 'not_submitted';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ERROR = 'error';

    public function __construct(
        private readonly TenantManager $tenants,
        private readonly TenantAuditLogger $audit,
        private readonly KsefClient $authClient,
        private readonly KsefHttpClient $httpClient,
        private readonly KsefInvoiceXmlBuilder $xmlBuilder,
    ) {}

    /**
     * Wyślij fakturę do KSeF. Buduje XML, autoryzuje cert flow z embedded
     * AES key, wysyła zaszyfrowany payload, zapisuje stan na invoice.
     *
     * Idempotency: caller powinien sprawdzić `ksef_status` przed wywołaniem
     * (gdy ='accepted' — nie pchamy ponownie). Tu nie blokujemy — multiple
     * submits skończą się duplikatem po stronie MF (osobny KSeF reference
     * per submit), co jest valid use-case (poprzedni submit timed out).
     */
    public function submit(Invoice $invoice): TenantKsefSubmissionResult
    {
        $tenant = $this->tenants->current();
        if ($tenant === null) {
            return TenantKsefSubmissionResult::error('No active tenant context.', []);
        }

        if (! $this->authClient->isReady($tenant)) {
            return TenantKsefSubmissionResult::error(
                'KSeF nie jest skonfigurowany — wgraj cert + NIP w /app/ksef-settings.',
                ['reason' => 'not_configured'],
            );
        }

        // Buduj XML zanim wykonamy handshake — jeśli wpadnie krypto exception
        // mamy mniej rzeczy do cleanup'u.
        try {
            $xml = $this->xmlBuilder->build($invoice);
        } catch (Throwable $e) {
            $this->logError($invoice, 'xml_build_failed', ['error' => $e->getMessage()]);

            return $this->persistError(
                $invoice,
                'KSeF XML build failed: '.$this->cleanErrorMessage($e->getMessage()),
                ['exception' => get_class($e), 'message' => $this->cleanErrorMessage($e->getMessage())],
            );
        }

        // Cache XML zanim wykonamy POST — debug + retry source.
        $invoice->forceFill(['ksef_xml' => $xml])->save();

        // Handshake — fresh AES per submit (KsefSessionManager dla cert flow = follow-up).
        try {
            $auth = $this->authClient->authenticateWithEncryptionKey($tenant);
        } catch (Throwable $e) {
            return $this->persistError(
                $invoice,
                'KSeF auth failed: '.$this->cleanErrorMessage($e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $this->cleanErrorMessage($e->getMessage()),
                    'received_at' => now()->toIso8601String(),
                ],
            );
        }

        // POST invoice
        $environment = $this->environment($tenant);
        try {
            $result = $this->httpClient->sendInvoice(
                environment: $environment,
                sessionToken: $auth['session_token'],
                aesKey: $auth['aes_key'],
                invoiceXml: $xml,
            );
        } catch (KsefApiException $e) {
            return $this->persistError(
                $invoice,
                'KSeF send failed: '.$e->getMessage(),
                $e->payload,
                isReject: $e->httpStatus >= 400 && $e->httpStatus < 500,
            );
        } catch (Throwable $e) {
            $this->logError($invoice, 'send_exception', ['error' => $e->getMessage()]);

            return $this->persistError(
                $invoice,
                'KSeF send failed: '.$this->cleanErrorMessage($e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $this->cleanErrorMessage($e->getMessage()),
                    'received_at' => now()->toIso8601String(),
                ],
            );
        }

        $reference = $result['element_reference_number'];

        $invoice->forceFill([
            'ksef_status' => self::STATUS_SUBMITTED,
            'ksef_reference_number' => $reference,
            'ksef_reference' => $reference, // legacy compat z poprzednim KSeF action
            'ksef_submitted_at' => now(),
            'ksef_sent_at' => now(),
            'ksef_environment' => $environment,
            'ksef_error_payload' => null,
        ])->save();

        $this->audit->record('invoice.ksef_submitted', 'Invoice', (string) $invoice->id, [
            'number' => $invoice->number,
            'reference_number' => $reference,
            'env' => $environment,
        ]);

        return TenantKsefSubmissionResult::submitted($reference);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function persistError(Invoice $invoice, string $message, array $payload, bool $isReject = false): TenantKsefSubmissionResult
    {
        $status = $isReject ? self::STATUS_REJECTED : self::STATUS_ERROR;

        $invoice->forceFill([
            'ksef_status' => $status,
            'ksef_error_payload' => $payload,
            'ksef_environment' => $this->environment($this->tenants->current()),
        ])->save();

        $this->logError($invoice, $isReject ? 'rejected' : 'error', [
            'message' => $message,
            'http_status' => $payload['status'] ?? null,
        ]);

        $this->audit->record(
            $isReject ? 'invoice.ksef_rejected' : 'invoice.ksef_error',
            'Invoice',
            (string) $invoice->id,
            ['number' => $invoice->number, 'message' => $message],
        );

        return $isReject
            ? TenantKsefSubmissionResult::rejected($message, $payload)
            : TenantKsefSubmissionResult::error($message, $payload);
    }

    /**
     * Sprawdź status wcześniej wysłanej faktury w KSeF. Używane przez UI
     * ("Odśwież status") oraz przez scheduled command (`ksef:poll-tenant-
     * invoices` — follow-up) co N minut dla wierszy w stanie 'submitted'.
     *
     * Wymaga że invoice ma `ksef_reference_number` ustawiony (czyli był
     * wcześniej submit'owany). Bez tego nie ma czego pollować.
     *
     * Mapowanie ProcessingCode MF (per dokumentacja KSeF API):
     *   - 100 (waiting), 110 (in progress), 300/305/315 (pending) → still 'submitted'
     *   - 200 (accepted z numerem KSeF) → 'accepted' + ksef_accepted_at
     *   - 4xx (merytoryczne błędy: zły NIP, brak P_15, etc.) → 'rejected'
     *   - 5xx (techniczne błędy po stronie MF) → 'error'
     */
    public function refreshStatus(Invoice $invoice): TenantKsefStatusResult
    {
        $tenant = $this->tenants->current();
        if ($tenant === null) {
            return TenantKsefStatusResult::error('No active tenant context.', []);
        }

        if (! $this->authClient->isReady($tenant)) {
            return TenantKsefStatusResult::error(
                'KSeF nie jest skonfigurowany — wgraj cert + NIP w /app/ksef-settings.',
                ['reason' => 'not_configured'],
            );
        }

        $reference = (string) ($invoice->ksef_reference_number ?? $invoice->ksef_reference ?? '');
        if ($reference === '') {
            return TenantKsefStatusResult::error('No reference number on invoice — nigdy nie został submit\'owany.', []);
        }

        // Polling też wymaga session token, ale NIE potrzebuje AES key
        // (Status endpoint nie szyfruje request body). Używamy auth-only
        // żeby uniknąć zbędnego embedded encryption key.
        try {
            $sessionToken = $this->authClient->authenticate($tenant);
        } catch (Throwable $e) {
            $this->logError($invoice, 'refresh_status_auth_failed', ['error' => $e->getMessage()]);

            return TenantKsefStatusResult::error(
                'KSeF auth (refresh status) failed: '.$this->cleanErrorMessage($e->getMessage()),
                [
                    'exception' => get_class($e),
                    'message' => $this->cleanErrorMessage($e->getMessage()),
                ],
            );
        }

        $environment = $this->environment($tenant);
        try {
            $status = $this->httpClient->getInvoiceStatus(
                environment: $environment,
                sessionToken: $sessionToken,
                invoiceElementReference: $reference,
            );
        } catch (KsefApiException $e) {
            $invoice->forceFill([
                'ksef_status' => self::STATUS_ERROR,
                'ksef_error_payload' => $e->payload,
            ])->save();

            return TenantKsefStatusResult::error('KSeF HTTP '.$e->httpStatus, $e->payload);
        } catch (Throwable $e) {
            $this->logError($invoice, 'refresh_status_exception', ['error' => $e->getMessage()]);

            return TenantKsefStatusResult::error(
                'KSeF status call failed: '.$this->cleanErrorMessage($e->getMessage()),
                ['exception' => get_class($e), 'message' => $this->cleanErrorMessage($e->getMessage())],
            );
        }

        return $this->mapStatusResponse($invoice, $reference, $status);
    }

    /**
     * Mapuje odpowiedź KSeF /Invoice/Status na lokalny status enum
     * i persistuje na invoice.
     *
     * @param  array{processing_code: string, processing_description: string, ksef_reference_number: ?string, raw_body: array<string,mixed>, http_status: int}  $status
     */
    private function mapStatusResponse(Invoice $invoice, string $reference, array $status): TenantKsefStatusResult
    {
        $code = $status['processing_code'];
        $desc = $status['processing_description'];
        $payload = [
            'status' => $status['http_status'],
            'body' => $status['raw_body'],
            'received_at' => now()->toIso8601String(),
        ];

        // 200 = accepted (z numerem KSeF)
        if ($code === '200') {
            $invoice->forceFill([
                'ksef_status' => self::STATUS_ACCEPTED,
                'ksef_accepted_at' => now(),
                'ksef_reference_number' => $status['ksef_reference_number'] ?? $reference,
                'ksef_error_payload' => null,
            ])->save();

            $this->audit->record('invoice.ksef_accepted', 'Invoice', (string) $invoice->id, [
                'number' => $invoice->number,
                'reference_number' => $reference,
            ]);

            return TenantKsefStatusResult::accepted($reference);
        }

        // 100/110/300/305/315 = pending — wciąż submitted, MF jeszcze procesuje
        if (in_array($code, ['100', '110', '300', '305', '315'], true)) {
            return TenantKsefStatusResult::pending($reference);
        }

        $intCode = (int) $code;

        // 5xx = error techniczny po stronie MF
        if ($intCode >= 500 && $intCode < 600) {
            $invoice->forceFill([
                'ksef_status' => self::STATUS_ERROR,
                'ksef_error_payload' => $payload,
            ])->save();

            return TenantKsefStatusResult::error('KSeF processing error '.$code, $payload);
        }

        // 4xx i inne = rejection
        $invoice->forceFill([
            'ksef_status' => self::STATUS_REJECTED,
            'ksef_error_payload' => $payload,
        ])->save();

        $this->audit->record('invoice.ksef_rejected', 'Invoice', (string) $invoice->id, [
            'number' => $invoice->number,
            'reference_number' => $reference,
            'processing_code' => $code,
        ]);

        return TenantKsefStatusResult::rejected($desc !== '' ? $desc : 'KSeF rejected', $payload, $reference);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logError(Invoice $invoice, string $event, array $context): void
    {
        Log::warning('ksef.tenant.'.$event, array_merge($context, [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
        ]));
    }

    private function environment(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return KsefHttpClient::ENV_TEST;
        }

        return match (strtolower((string) (data_get($tenant->settings, 'ksef.env') ?? 'test'))) {
            'prod', 'production' => KsefHttpClient::ENV_PROD,
            'demo' => KsefHttpClient::ENV_DEMO,
            default => KsefHttpClient::ENV_TEST,
        };
    }

    /**
     * Scrub potentially-sensitive long alphanumeric strings (tokeny, klucze)
     * z error message przed log'iem / persist'em.
     */
    private function cleanErrorMessage(string $message): string
    {
        return (string) preg_replace('/[A-Za-z0-9+\/]{20,}/', '[REDACTED]', $message);
    }
}
