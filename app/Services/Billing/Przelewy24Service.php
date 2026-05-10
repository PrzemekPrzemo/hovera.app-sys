<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Centralny (master-admin) integration z Przelewy24 dla SaaS FV
 * hovery. Każda stajnia która chce zapłacić fakturę jednorazowo (np.
 * proforma, doładowanie usług) dostaje link "Opłać fakturę" generowany
 * przez ten serwis — komplementarny do Stripe Checkout (recurring).
 *
 * UWAGA: Per-tenant P24 (stajnia → klient) żyje w
 * App\Services\Payments\Providers\P24PaymentProvider — TAMTO jest
 * używane gdy stajnia inkasuje od jeźdźca. Tutaj jest płatność
 * stajnia → hovera za nasz SaaS.
 *
 * Sign formulas (SHA384 of JSON_UNESCAPED_SLASHES JSON):
 *   register:    {sessionId, merchantId, amount, currency, crc}
 *   webhook in:  {merchantId, posId, sessionId, amount, originAmount,
 *                 currency, orderId, methodId, statement, crc}
 *   verify:      {sessionId, orderId, amount, currency, crc}
 *
 * Reference: https://docs.przelewy24.pl/
 */
class Przelewy24Service
{
    public const PROD_HOST = 'https://secure.przelewy24.pl';

    public const SANDBOX_HOST = 'https://sandbox.przelewy24.pl';

    public function __construct(
        private readonly int $merchantId,
        private readonly int $posId,
        private readonly string $apiKey,
        private readonly string $crc,
        private readonly string $env = 'sandbox',
    ) {
        if ($merchantId === 0) {
            throw new InvalidArgumentException('P24_MERCHANT_ID is empty.');
        }
        if ($apiKey === '' || $crc === '') {
            throw new InvalidArgumentException('P24 api_key / crc is empty.');
        }
        if (! in_array($env, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException("Unknown P24 env: {$env}");
        }
    }

    /**
     * Rejestruje transakcję w P24 i zwraca pełny URL do hostowanej
     * płatności. Zapisuje session_id + order_id + url na fakturze.
     *
     * @throws RuntimeException gdy P24 zwróci błąd / pusty token
     */
    public function createPayment(Invoice $invoice): string
    {
        if ($invoice->currency !== 'PLN') {
            throw new InvalidArgumentException('P24 supports PLN only — invoice currency: '.$invoice->currency);
        }
        if ($invoice->total_cents <= 0) {
            throw new InvalidArgumentException('Invoice total must be > 0.');
        }

        $sessionId = $invoice->id;
        $amount = $invoice->total_cents; // grosze
        $sign = $this->signRegister($sessionId, $amount);

        $email = $this->resolveEmail($invoice);

        $payload = [
            'merchantId' => $this->merchantId,
            'posId' => $this->posId,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => $invoice->currency,
            'description' => $this->description($invoice),
            'email' => $email,
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => route('payments.p24.return', ['invoiceId' => $invoice->id]),
            'urlStatus' => route('webhooks.p24'),
            'sign' => $sign,
        ];

        $response = Http::withBasicAuth((string) $this->posId, $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($this->host().'/api/v1/transaction/register', $payload);

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'error', 'unknown error');
            throw new RuntimeException("P24 register failed: {$err} (HTTP {$response->status()})");
        }

        $token = (string) data_get($response->json(), 'data.token', '');
        if ($token === '') {
            throw new RuntimeException('P24 register: empty token in response.');
        }

        $url = $this->host().'/trnRequest/'.$token;

        $invoice->forceFill([
            'p24_session_id' => $sessionId,
            'p24_payment_url' => $url,
        ])->save();

        return $url;
    }

    /**
     * Weryfikuje sign z webhooku (P24 notification). Zwraca true gdy
     * sign matches naszą rekonstrukcją z CRC.
     *
     * @param  array<string,mixed>  $payload
     */
    public function verifyWebhook(array $payload): bool
    {
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
        );

        return hash_equals($expected, $sign);
    }

    /**
     * Drugie wywołanie do P24 (po sign-check) potwierdzające że
     * transakcja faktycznie się powiodła i zatwierdzające captures.
     */
    public function verifyPayment(string $sessionId, int $orderId, int $amountGrosze, string $currency = 'PLN'): bool
    {
        $sign = $this->signVerify($sessionId, $orderId, $amountGrosze, $currency);
        $payload = [
            'merchantId' => $this->merchantId,
            'posId' => $this->posId,
            'sessionId' => $sessionId,
            'amount' => $amountGrosze,
            'currency' => $currency,
            'orderId' => $orderId,
            'sign' => $sign,
        ];

        $response = Http::withBasicAuth((string) $this->posId, $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->put($this->host().'/api/v1/transaction/verify', $payload);

        if (! $response->successful()) {
            Log::warning('P24 verify HTTP error', [
                'session_id' => $sessionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return (string) data_get($response->json(), 'data.status') === 'success';
    }

    /**
     * Convenience — pełen flow webhooku: weryfikuj sign, znajdź FV,
     * zawołaj /verify, oznacz jako zapłaconą. Zwraca true gdy zapis
     * poszedł, false gdy coś niezgodne (sign / nie-znaleziona FV /
     * verify failed). Idempotent — drugi webhook na zapłaconą FV nie
     * robi nic poza zwróceniem true.
     *
     * @param  array<string,mixed>  $payload
     */
    public function processWebhook(array $payload): bool
    {
        if (! $this->verifyWebhook($payload)) {
            Log::warning('P24 webhook: signature mismatch', [
                'session_id' => $payload['sessionId'] ?? null,
            ]);

            return false;
        }

        $sessionId = (string) ($payload['sessionId'] ?? '');
        $orderId = (int) ($payload['orderId'] ?? 0);
        $amount = (int) ($payload['amount'] ?? 0);
        $currency = (string) ($payload['currency'] ?? 'PLN');

        $invoice = Invoice::query()->where('p24_session_id', $sessionId)->first();
        if ($invoice === null) {
            Log::warning('P24 webhook: invoice not found', ['session_id' => $sessionId]);

            return false;
        }

        // Idempotency — already paid, just ack.
        if ($invoice->isPaid()) {
            return true;
        }

        if ($amount !== $invoice->total_cents) {
            Log::warning('P24 webhook: amount mismatch', [
                'session_id' => $sessionId,
                'expected' => $invoice->total_cents,
                'got' => $amount,
            ]);

            return false;
        }

        $verified = $this->verifyPayment($sessionId, $orderId, $amount, $currency);
        if (! $verified) {
            return false;
        }

        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'p24_paid_at' => now(),
            'p24_order_id' => (string) $orderId,
        ])->save();

        return true;
    }

    private function signRegister(string $sessionId, int $amount, string $currency = 'PLN'): string
    {
        return hash('sha384', json_encode([
            'sessionId' => $sessionId,
            'merchantId' => $this->merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $this->crc,
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
            'crc' => $this->crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function signVerify(string $sessionId, int $orderId, int $amount, string $currency): string
    {
        return hash('sha384', json_encode([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $this->crc,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function host(): string
    {
        return $this->env === 'production' ? self::PROD_HOST : self::SANDBOX_HOST;
    }

    private function description(Invoice $invoice): string
    {
        $plan = $invoice->plan_code !== null ? ' ('.$invoice->plan_code.')' : '';

        return 'Hovera FV '.$invoice->number.$plan;
    }

    private function resolveEmail(Invoice $invoice): string
    {
        $tenant = $invoice->tenant;
        if ($tenant === null) {
            return 'no-reply@hovera.app';
        }

        $email = $tenant->memberships()
            ->where('role', 'owner')
            ->whereNull('revoked_at')
            ->orderBy('joined_at')
            ->limit(1)
            ->get()
            ->map(fn ($m) => $m->user?->email)
            ->filter()
            ->first();

        return is_string($email) && $email !== '' ? $email : 'no-reply@hovera.app';
    }
}
