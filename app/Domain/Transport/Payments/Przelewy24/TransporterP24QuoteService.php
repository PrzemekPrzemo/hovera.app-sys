<?php

declare(strict_types=1);

namespace App\Domain\Transport\Payments\Przelewy24;

use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Per-transporter P24 dla ofert transportowych — quote payments via
 * Przelewy24. Patrz docs/TRANSPORT.md §15.5.
 *
 * KEY DESIGN: Hovera NIE jest tu merchant of record. Credentials
 * (merchant_id, pos_id, crc, api_key) trzymane w
 * `tenants.settings.payments.p24` (encrypted) — TO SAMO źródło co
 * `App\Services\Payments\Providers\P24PaymentProvider` (per-tenant
 * payments stable side). Reusing istniejący storage żeby transporter
 * konfigurował creds raz w /app/payment-settings.
 *
 * Różnice względem `P24PaymentProvider`:
 *   - target jest Quote (transport), nie Payment (booking lekcji)
 *   - tracking inline na Quote (p24_session_id, p24_payment_url, ...)
 *     bo Quote nie ma client_id (wymaganego przez Payment)
 *   - return URL ląduje na quote landing pages (`public.transport.quote`)
 *   - webhook leci do Hovery (`public.transport.p24.webhook`) — opcjonalny
 *     bo MVP polega na manual "Oznacz jako opłacone" (opcja B)
 *
 * Sign formulas (SHA384 of JSON_UNESCAPED_SLASHES JSON):
 *   register:    {sessionId, merchantId, amount, currency, crc}
 *   webhook in:  {merchantId, posId, sessionId, amount, originAmount,
 *                 currency, orderId, methodId, statement, crc}
 *   verify:      {sessionId, orderId, amount, currency, crc}
 *
 * Reference: https://docs.przelewy24.pl/
 */
class TransporterP24QuoteService
{
    public const PROD_HOST = 'https://secure.przelewy24.pl';

    public const SANDBOX_HOST = 'https://sandbox.przelewy24.pl';

    /**
     * Walidacja konfiguracji: czy transporter ma wszystkie wymagane
     * fields w settings + czy P24 quote autopay jest enabled w
     * TransportSettings.
     */
    public function isConfigured(Tenant $tenant): bool
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.p24') ?? []);
        foreach (['merchant_id', 'pos_id', 'crc_key', 'api_key'] as $k) {
            if (empty($cfg[$k])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rejestruje transakcję P24 dla quote'u i zwraca pełny URL hostowanej
     * płatności. Zapisuje session_id + url na Quote.
     *
     * @throws RuntimeException gdy P24 zwróci błąd / pusty token
     * @throws InvalidArgumentException gdy quote nie spełnia wymagań
     *                                  (waluta, kwota, brak creds)
     */
    public function createPaymentSession(Tenant $tenant, Quote $quote): string
    {
        if (! $this->isConfigured($tenant)) {
            throw new InvalidArgumentException('Transporter nie ma skonfigurowanego P24 (tenant.settings.payments.p24).');
        }

        // P24 oficjalnie wspiera tylko PLN dla większości metod (BLIK,
        // przelewy). Karty obsługują EUR/USD/CZK/GBP, ale dla MVP
        // ograniczamy do PLN — fallback do `payment_url` template'a dla
        // walut zagranicznych.
        if ($quote->currency !== 'PLN') {
            throw new InvalidArgumentException('P24 quote autopay wspiera tylko PLN — quote currency: '.$quote->currency);
        }

        $grossPln = (float) $quote->gross_total;
        if ($grossPln <= 0) {
            throw new InvalidArgumentException('Quote gross_total musi być > 0 dla P24.');
        }

        $cfg = $this->resolveCredentials($tenant);
        $host = $this->host($cfg);

        $sessionId = (string) $quote->id;
        $amount = (int) round($grossPln * 100); // grosze
        $sign = $this->signRegister($sessionId, $cfg['merchant_id'], $amount, $quote->currency, $cfg['crc_key']);

        $payload = [
            'merchantId' => $cfg['merchant_id'],
            'posId' => $cfg['pos_id'],
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => $quote->currency,
            'description' => $this->description($quote),
            'email' => $quote->customer_email ?: 'no-reply@hovera.app',
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => route('public.transport.p24.return', [
                'tenant_slug' => $tenant->slug,
                'quote_id' => $quote->id,
            ]),
            // Webhook URL hostowany przez Hovera — opcjonalny. Transporter
            // może też skonfigurować własny URL bezpośrednio w panelu P24
            // (wtedy Hovera nigdy nie dostanie notification). MVP: manual
            // "Oznacz jako opłacone" w `/transport/quotes` zawsze działa
            // jako fallback.
            'urlStatus' => route('public.transport.p24.webhook', [
                'tenant_slug' => $tenant->slug,
            ]),
            'sign' => $sign,
        ];

        $response = Http::withBasicAuth((string) $cfg['pos_id'], $cfg['api_key'])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($host.'/api/v1/transaction/register', $payload);

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'error', 'unknown error');
            throw new RuntimeException("P24 quote register failed: {$err} (HTTP {$response->status()})");
        }

        $token = (string) data_get($response->json(), 'data.token', '');
        if ($token === '') {
            throw new RuntimeException('P24 quote register: empty token in response.');
        }

        $url = $host.'/trnRequest/'.$token;

        $quote->forceFill([
            'p24_session_id' => $sessionId,
            'p24_payment_url' => $url,
        ])->save();

        return $url;
    }

    /**
     * Verify webhook signature — analogous do
     * Przelewy24Service::verifyWebhook, ale używa creds transportera
     * z tenant.settings (a nie central config).
     *
     * @param  array<string,mixed>  $payload
     */
    public function verifyWebhook(Tenant $tenant, array $payload): bool
    {
        if (! $this->isConfigured($tenant)) {
            return false;
        }
        $cfg = $this->resolveCredentials($tenant);

        $sign = (string) ($payload['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        $expected = $this->signWebhook(
            (int) ($payload['merchantId'] ?? 0),
            (int) ($payload['posId'] ?? 0),
            (string) ($payload['sessionId'] ?? ''),
            (int) ($payload['amount'] ?? 0),
            (int) ($payload['originAmount'] ?? 0),
            (string) ($payload['currency'] ?? ''),
            (int) ($payload['orderId'] ?? 0),
            (int) ($payload['methodId'] ?? 0),
            (string) ($payload['statement'] ?? ''),
            $cfg['crc_key'],
        );

        return hash_equals($expected, $sign);
    }

    /**
     * Drugie wywołanie P24 (po sign-check) potwierdzające że transakcja
     * faktycznie się powiodła. Zwraca true gdy P24 odpowie status=success.
     */
    public function verifyPayment(Tenant $tenant, string $sessionId, int $orderId, int $amountGrosze, string $currency = 'PLN'): bool
    {
        $cfg = $this->resolveCredentials($tenant);
        $host = $this->host($cfg);

        $sign = $this->signVerify($sessionId, $orderId, $amountGrosze, $currency, $cfg['crc_key']);
        $payload = [
            'merchantId' => $cfg['merchant_id'],
            'posId' => $cfg['pos_id'],
            'sessionId' => $sessionId,
            'amount' => $amountGrosze,
            'currency' => $currency,
            'orderId' => $orderId,
            'sign' => $sign,
        ];

        $response = Http::withBasicAuth((string) $cfg['pos_id'], $cfg['api_key'])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->put($host.'/api/v1/transaction/verify', $payload);

        if (! $response->successful()) {
            Log::warning('P24 quote verify HTTP error', [
                'tenant' => $tenant->slug,
                'session_id' => $sessionId,
                'status' => $response->status(),
            ]);

            return false;
        }

        return (string) data_get($response->json(), 'data.status') === 'success';
    }

    /**
     * Full webhook processing: verify sign, find quote, call /verify,
     * flip quote.payment_completed_at + p24_paid_at. Idempotent.
     *
     * @param  array<string,mixed>  $payload
     */
    public function processWebhook(Tenant $tenant, array $payload): bool
    {
        if (! $this->verifyWebhook($tenant, $payload)) {
            Log::warning('P24 quote webhook: signature mismatch', [
                'tenant' => $tenant->slug,
                'session_id' => $payload['sessionId'] ?? null,
            ]);

            return false;
        }

        $sessionId = (string) ($payload['sessionId'] ?? '');
        $orderId = (int) ($payload['orderId'] ?? 0);
        $amount = (int) ($payload['amount'] ?? 0);
        $currency = (string) ($payload['currency'] ?? 'PLN');

        $quote = Quote::query()->where('p24_session_id', $sessionId)->first();
        if ($quote === null) {
            Log::warning('P24 quote webhook: quote not found', [
                'tenant' => $tenant->slug,
                'session_id' => $sessionId,
            ]);

            return false;
        }

        // Idempotency — już oznaczony, ack.
        if ($quote->p24_paid_at !== null) {
            return true;
        }

        $expectedAmount = (int) round((float) $quote->gross_total * 100);
        if ($amount !== $expectedAmount) {
            Log::warning('P24 quote webhook: amount mismatch', [
                'tenant' => $tenant->slug,
                'session_id' => $sessionId,
                'expected' => $expectedAmount,
                'got' => $amount,
            ]);

            return false;
        }

        $verified = $this->verifyPayment($tenant, $sessionId, $orderId, $amount, $currency);
        if (! $verified) {
            return false;
        }

        $quote->forceFill([
            'p24_paid_at' => now(),
            'p24_order_id' => (string) $orderId,
            // Auto-flip payment_completed_at jeśli jeszcze nie był ręcznie
            // ustawiony (np. transporter już oznaczył offline). Po prostu
            // używamy P24 jako źródła prawdy gdy webhook dotarł first.
            'payment_completed_at' => $quote->payment_completed_at ?? now(),
            'payment_method_label' => $quote->payment_method_label ?: 'Przelewy24',
        ])->save();

        return true;
    }

    private function signRegister(string $sessionId, int $merchantId, int $amount, string $currency, string $crc): string
    {
        return hash('sha384', json_encode([
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function signWebhook(
        int $merchantId,
        int $posId,
        string $sessionId,
        int $amount,
        int $originAmount,
        string $currency,
        int $orderId,
        int $methodId,
        string $statement,
        string $crc,
    ): string {
        return hash('sha384', json_encode([
            'merchantId' => $merchantId,
            'posId' => $posId,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'originAmount' => $originAmount,
            'currency' => $currency,
            'orderId' => $orderId,
            'methodId' => $methodId,
            'statement' => $statement,
            'crc' => $crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function signVerify(string $sessionId, int $orderId, int $amount, string $currency, string $crc): string
    {
        return hash('sha384', json_encode([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{merchant_id:int, pos_id:int, crc_key:string, api_key:string, sandbox:bool}
     */
    private function resolveCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.p24') ?? []);

        $crc = (string) ($cfg['crc_key'] ?? '');
        try {
            $crc = Crypt::decryptString($crc);
        } catch (\Throwable) {
            // plain-text fallback (testy z plain crc)
        }
        $apiKey = (string) ($cfg['api_key'] ?? '');
        try {
            $apiKey = Crypt::decryptString($apiKey);
        } catch (\Throwable) {
        }

        return [
            'merchant_id' => (int) ($cfg['merchant_id'] ?? 0),
            'pos_id' => (int) ($cfg['pos_id'] ?? ($cfg['merchant_id'] ?? 0)),
            'crc_key' => $crc,
            'api_key' => $apiKey,
            'sandbox' => (bool) ($cfg['sandbox'] ?? false),
        ];
    }

    /**
     * @param  array{sandbox:bool}  $cfg
     */
    private function host(array $cfg): string
    {
        return $cfg['sandbox'] ? self::SANDBOX_HOST : self::PROD_HOST;
    }

    private function description(Quote $quote): string
    {
        return 'Transport oferta '.$quote->number;
    }
}
