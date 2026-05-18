<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef;

use App\Enums\TenantType;
use App\Enums\TransportKsefStatus;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\TransportSettings;
use App\Services\Ksef\CentralKsefService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-transporter KSeF — passthrough layer dla `TransportInvoice`.
 *
 * Hovera NIE jest wystawcą faktur transportowych (patrz docs/TRANSPORT.md
 * §12: marketplace positioning). Każdy transporter wprowadza WŁASNY
 * token autoryzacyjny KSeF w `transport_settings.ksef_token_encrypted`,
 * WŁASNY NIP, i wybiera środowisko (test/prod).
 *
 * Ten serwis:
 *   - sięga po credentials z `TransportSettings` aktywnego tenanta;
 *   - buduje payload FA(2/3) (reuse `CentralKsefService::buildInvoiceXml`
 *     z lekkim post-processingiem nagłówka — XML jest semantycznie
 *     identyczny po stronie struktury, różni się tylko `SystemInfo`);
 *   - wykonuje HTTP do KSeF (test / demo / prod) z nagłówkiem
 *     `SessionToken` = token transportera;
 *   - utrwala wynik (status, reference_number, xml cache, error payload);
 *   - loguje przez TENANT audit logger (NIE master) — to akcja
 *     transportera, nie Hovery.
 *
 * Token NIGDY nie pojawia się w wyjątkach ani logach. Do debug/ops
 * używamy `TransportSettings::redactedTokenPreview()`.
 *
 * UWAGA: pełna integracja KSeF wymaga session lifecycle (challenge +
 * AuthTokenRequest + InitToken + EncryptedSession), my w tym PR
 * dostarczamy MVP: token KSeF zdobyty przez transportera w MF służy
 * bezpośrednio jako auth header. Pełen handshake odkładamy do follow-up
 * (analogicznie jak `CentralKsefService` skeleton).
 */
class TransporterKsefService
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly TenantAuditLogger $audit,
        private readonly CentralKsefService $xmlBuilder,
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
     * POST, zapisuje stan na FV. Nigdy nie rzuca wyjątkiem zawierającym
     * token; błędy HTTP są mapowane na `KsefSubmissionResult::error()`.
     *
     * @throws KsefNotConfiguredException gdy brakuje credentials
     */
    public function submit(TransportInvoice $invoice): KsefSubmissionResult
    {
        $settings = $this->assertConfigured();

        $xml = $this->generateXml($invoice);

        // Cache XML zanim wykonamy POST — jeśli MF padnie / timeout,
        // mamy ślad co próbowaliśmy wysłać (do retry / debug).
        $invoice->forceFill([
            'ksef_xml' => $xml,
        ])->save();

        try {
            $response = $this->client($settings)
                ->withBody($xml, 'application/octet-stream')
                ->post($this->host($settings).'/online/Invoice/Send');
        } catch (Throwable $e) {
            return $this->handleException($invoice, $settings, $e);
        }

        if ($response->successful()) {
            $reference = (string) ($response->json('elementReferenceNumber')
                ?? $response->json('referenceNumber')
                ?? '');

            if ($reference === '') {
                return $this->persistError(
                    $invoice,
                    $settings,
                    'KSeF returned 200 but no referenceNumber',
                    $this->safePayload($response),
                );
            }

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
                'env' => $settings->ksef_environment,
                'token_preview' => $settings->redactedTokenPreview(),
            ]);

            return KsefSubmissionResult::submitted($reference);
        }

        // 4xx / 5xx
        $payload = $this->safePayload($response);

        return $this->persistError(
            $invoice,
            $settings,
            'KSeF HTTP '.$response->status(),
            $payload,
            isReject: $response->status() >= 400 && $response->status() < 500,
        );
    }

    /**
     * Sprawdź status wcześniejszego submit. Używane przez UI ("Odśwież
     * status") oraz przyszły cron pollujący `submitted` starsze niż X.
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
            $response = $this->client($settings)
                ->acceptJson()
                ->get($this->host($settings).'/online/Invoice/Status/'.$reference);
        } catch (Throwable $e) {
            $this->logError($settings, 'refresh_status_http_exception', ['error' => $e->getMessage()]);

            return KsefStatusResult::error('KSeF status call failed: '.$e->getMessage(), []);
        }

        if (! $response->successful()) {
            $payload = $this->safePayload($response);
            $invoice->forceFill([
                'ksef_status' => TransportKsefStatus::Error,
                'ksef_error_payload' => $payload,
            ])->save();

            return KsefStatusResult::error('KSeF HTTP '.$response->status(), $payload);
        }

        $statusCode = (string) $response->json('processingCode');
        $statusDesc = (string) $response->json('processingDescription');

        // Mapowanie ProcessingCode MF:
        //   100 = ok, accepted; 200/300 = in progress; 400+ = błąd
        if ($statusCode === '200' || $statusCode === '100') {
            $invoice->forceFill([
                'ksef_status' => TransportKsefStatus::Accepted,
                'ksef_accepted_at' => now(),
                'ksef_error_payload' => null,
            ])->save();

            $this->audit->record('transport_invoice.ksef_accepted', 'TransportInvoice', (string) $invoice->id, [
                'number' => $invoice->number,
                'reference_number' => $reference,
            ]);

            return KsefStatusResult::accepted($reference);
        }

        if ($statusCode === '300' || $statusCode === '305' || $statusCode === '315') {
            // Wciąż pending.
            return KsefStatusResult::pending($reference);
        }

        // Odrzucenie / błąd biznesowy.
        $payload = $this->safePayload($response);
        $invoice->forceFill([
            'ksef_status' => TransportKsefStatus::Rejected,
            'ksef_error_payload' => $payload,
        ])->save();

        $this->audit->record('transport_invoice.ksef_rejected', 'TransportInvoice', (string) $invoice->id, [
            'number' => $invoice->number,
            'reference_number' => $reference,
            'processing_code' => $statusCode,
        ]);

        return KsefStatusResult::rejected($statusDesc ?: 'KSeF rejected', $payload, $reference);
    }

    /**
     * Buduje XML FA dla transport FV. Reuse builderu z CentralKsefService
     * + lekka adaptacja: tag `<SystemInfo>` mówi "Hovera Transport
     * Passthrough", tak by audyt MF mógł rozpoznać że Hovera jest tylko
     * software'em, nie wystawcą.
     *
     * UWAGA: `CentralKsefService::buildInvoiceXml` przyjmuje
     * `App\Models\Central\Invoice`, my mamy `Tenant\TransportInvoice` —
     * inny schemat. Budujemy własny XML, ale identyczny strukturalnie.
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
            .'</Fa>'
            .'</Faktura>';
    }

    /**
     * Lekki "ping" — pyta KSeF czy token jest ważny (najtańszy
     * auth-only endpoint). Używany przez Settings page "Test connection".
     * Zwraca [success, message]; NIGDY nie ujawnia tokenu.
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
            $response = $this->client($settings)
                ->acceptJson()
                ->get($this->host($settings).'/online/Session/Status');
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'KSeF unreachable: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'KSeF '.$settings->ksef_environment.' OK'];
        }

        return [
            'success' => false,
            'message' => 'KSeF HTTP '.$response->status().' — sprawdź token i NIP w ustawieniach.',
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
            // Tylko sprawdzamy weryfikację dla kont typu transporter —
            // stajnie (typ inny) nie używają tego service'u w ogóle, ale
            // defensywnie ich nie blokujemy gdyby trafiły tu przez błąd.
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

    private function client(TransportSettings $settings): PendingRequest
    {
        // KSeF token = wartość trzymana w sekretnym nagłówku `SessionToken`
        // (tak nazywa to MF). Tutaj uproszczona ścieżka: token transportera
        // jest długoterminowym tokenem KSeF — pełen handshake (challenge +
        // RSA-OAEP) odkładamy do follow-up PR.
        $token = (string) $settings->getKsefToken();

        return Http::withHeaders([
            'SessionToken' => $token,
            'Accept' => 'application/json',
        ])->timeout(30);
    }

    private function host(TransportSettings $settings): string
    {
        return match ((string) $settings->ksef_environment) {
            'production', 'prod' => CentralKsefService::HOST_PROD,
            'demo' => CentralKsefService::HOST_DEMO,
            default => CentralKsefService::HOST_TEST,
        };
    }

    /**
     * Zwraca payload odpowiedzi BEZPIECZNY do utrwalenia (bez tokenów,
     * bez headers, max 16KB). Logiczna ochrona przed przypadkowym
     * scrubowaniem secretów do bazy / logów.
     *
     * @return array<string,mixed>
     */
    private function safePayload(Response $response): array
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
        // Scrub: w exception message nie powinno być tokenu, ale defensywnie
        // nigdy nie propagujemy raw Throwable — wyciągamy tylko getMessage().
        $message = 'KSeF call failed: '.$e->getMessage();

        return $this->persistError($invoice, $settings, $message, [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
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
            // Świadomie NIE logujemy: pełnego tokenu, pełnego body, NIP w czystej formie.
        ]));
    }

    private function normalizeNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip) ?? '';
    }
}
