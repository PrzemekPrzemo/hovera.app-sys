<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Przelewy24 (P24) integration via REST API v1.
 *
 * Required tenant credentials (settings.payments.p24):
 *   merchant_id     numeric (e.g. 12345)
 *   pos_id          numeric (often == merchant_id for single-POS)
 *   crc_key         32-char string used for SHA384 signing       (encrypted)
 *   api_key         32-char REST API password (Basic auth pass)  (encrypted)
 *   sandbox         bool (true → sandbox.przelewy24.pl)
 *   force_method    optional single method id (np. 154 = BLIK)
 *
 * Flow:
 *   1. POST /api/v1/transaction/register  → {data:{token}}
 *   2. Redirect user to {host}/trnRequest/{token}
 *   3. P24 sends async POST do /payments/p24/webhook with sign+payload
 *   4. We verify SHA384 sign, then POST /api/v1/transaction/verify
 *   5. Verify response success → mark Succeeded
 *
 * Sign formulas (SHA384 of JSON_UNESCAPED_SLASHES JSON):
 *   register:    {sessionId, merchantId, amount, currency, crc}
 *   webhook in:  {merchantId, posId, sessionId, amount, originAmount,
 *                 currency, orderId, methodId, statement, crc}
 *   verify:      {sessionId, orderId, amount, currency, crc}
 *
 * Reference: https://docs.przelewy24.pl/
 */
class P24PaymentProvider implements PaymentProviderInterface
{
    public const PROD_HOST = 'https://secure.przelewy24.pl';

    public const SANDBOX_HOST = 'https://sandbox.przelewy24.pl';

    /**
     * Most common P24 method ids. Owner may pick one to force a specific
     * channel (e.g. force BLIK for tap-to-pay flows). Empty selection =
     * P24 shows full method picker (rekomendowane dla max conversion).
     *
     * @var array<int,string>
     */
    public const METHOD_LABELS = [
        154 => 'BLIK',
        25 => 'Karty (Visa / Mastercard)',
        177 => 'Google Pay',
        178 => 'Apple Pay',
        85 => 'Karta z bankowości',
    ];

    /**
     * @return array<string,string>
     */
    public static function methodOptions(): array
    {
        $out = [];
        foreach (self::METHOD_LABELS as $id => $label) {
            $out[(string) $id] = $label;
        }

        return $out;
    }

    public function id(): string
    {
        return 'p24';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $cfg = $this->resolveCredentials($tenant);
        $host = $cfg['sandbox'] ? self::SANDBOX_HOST : self::PROD_HOST;

        $sessionId = $payment->id;
        $sign = $this->signRegister($sessionId, $cfg['merchant_id'], $payment->amount_cents, $payment->currency, $cfg['crc_key']);

        $payload = [
            'merchantId' => $cfg['merchant_id'],
            'posId' => $cfg['pos_id'],
            'sessionId' => $sessionId,
            'amount' => $payment->amount_cents,
            'currency' => $payment->currency,
            'description' => $this->description($payment, $tenant),
            'email' => (string) ($payment->client?->email ?? 'no-reply@hovera.app'),
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => route('public.payments.return', [
                'slug' => $tenant->slug,
                'provider' => 'p24',
                'payment' => $payment->id,
            ]),
            'urlStatus' => route('public.payments.webhook', [
                'slug' => $tenant->slug,
                'provider' => 'p24',
            ]),
            'sign' => $sign,
        ];

        // Optional force-method (np. 154 = BLIK). Pomijamy gdy 0/null.
        if (! empty($cfg['force_method'])) {
            $payload['method'] = (int) $cfg['force_method'];
        }

        $response = Http::withBasicAuth((string) $cfg['pos_id'], $cfg['api_key'])
            ->acceptJson()
            ->asJson()
            ->post($host.'/api/v1/transaction/register', $payload);

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'error', 'unknown error');
            throw PaymentProviderException::apiError($this->id(), $err);
        }

        $token = (string) data_get($response->json(), 'data.token', '');
        if ($token === '') {
            throw PaymentProviderException::apiError($this->id(), 'pusty token w odpowiedzi');
        }

        $checkoutUrl = $host.'/trnRequest/'.$token;

        $payment->forceFill([
            'provider_ref' => $token,
            'checkout_url' => $checkoutUrl,
            'status' => PaymentStatus::Processing->value,
            'expires_at' => now()->addMinutes(15), // P24 token TTL ~15 min
            'provider_data' => ['register_response' => $response->json(), 'host' => $host],
        ])->save();

        return $checkoutUrl;
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    /**
     * P24 notification: form-encoded payload with sign field. We verify
     * the sign, then call /api/v1/transaction/verify to confirm.
     */
    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        $cfg = $this->resolveCredentials($tenant);
        $host = $cfg['sandbox'] ? self::SANDBOX_HOST : self::PROD_HOST;

        $merchantId = (int) $request->input('merchantId', 0);
        $posId = (int) $request->input('posId', 0);
        $sessionId = (string) $request->input('sessionId', '');
        $amount = (int) $request->input('amount', 0);
        $originAmount = (int) $request->input('originAmount', 0);
        $currency = (string) $request->input('currency', '');
        $orderId = (int) $request->input('orderId', 0);
        $methodId = (int) $request->input('methodId', 0);
        $statement = (string) $request->input('statement', '');
        $sign = (string) $request->input('sign', '');

        if ($merchantId !== $cfg['merchant_id'] || $posId !== $cfg['pos_id']) {
            Log::warning('P24 webhook: merchant/pos mismatch', ['tenant' => $tenant->slug]);

            return response('mismatch', 400);
        }

        $expected = $this->signWebhook(
            $merchantId,
            $posId,
            $sessionId,
            $amount,
            $originAmount,
            $currency,
            $orderId,
            $methodId,
            $statement,
            $cfg['crc_key'],
        );
        if (! hash_equals($expected, $sign)) {
            Log::warning('P24 webhook: invalid sign', ['tenant' => $tenant->slug, 'sessionId' => $sessionId]);

            return response('invalid_sign', 400);
        }

        $payment = Payment::query()->where('id', $sessionId)->first();
        if (! $payment) {
            return response('not_found', 404);
        }

        // Verify call — confirms transaction with P24 + commits funds
        $verifySign = $this->signVerify($sessionId, $orderId, $amount, $currency, $cfg['crc_key']);
        $verifyPayload = [
            'merchantId' => $merchantId,
            'posId' => $posId,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => $currency,
            'orderId' => $orderId,
            'sign' => $verifySign,
        ];

        $verifyResp = Http::withBasicAuth((string) $cfg['pos_id'], $cfg['api_key'])
            ->acceptJson()
            ->asJson()
            ->put($host.'/api/v1/transaction/verify', $verifyPayload);

        $verified = $verifyResp->successful()
            && (string) data_get($verifyResp->json(), 'data.status') === 'success';

        // Idempotency
        if ($payment->status->isTerminal()) {
            return response('ok', 200);
        }

        $payment->forceFill([
            'status' => $verified ? PaymentStatus::Succeeded->value : PaymentStatus::Failed->value,
            'paid_at' => $verified ? now() : null,
            'provider_data' => array_merge((array) $payment->provider_data, [
                'verify_response' => $verifyResp->json(),
                'webhook_payload' => $request->all(),
                'order_id' => $orderId,
                'method_id' => $methodId,
            ]),
        ])->save();

        return response('ok', 200);
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        // P24 supports refunds via /api/v1/transaction/refund (asynchronous,
        // requires admin approval in their panel). Client integration is
        // niche; for MVP we mark refunded locally only after manual P24
        // panel action. Future: implement /transaction/refund call.
        throw PaymentProviderException::notImplemented(
            $this->id(),
            'refund — wykonaj w panelu Przelewy24, status zostanie zaktualizowany po webhooku',
        );
    }

    private function signRegister(string $sessionId, int $merchantId, int $amount, string $currency, string $crc): string
    {
        $payload = [
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ];

        return hash('sha384', json_encode($payload, JSON_UNESCAPED_SLASHES));
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
        $payload = [
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
        ];

        return hash('sha384', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function signVerify(string $sessionId, int $orderId, int $amount, string $currency, string $crc): string
    {
        $payload = [
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ];

        return hash('sha384', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{merchant_id:int, pos_id:int, crc_key:string, api_key:string, sandbox:bool, force_method:?int}
     */
    private function resolveCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.p24') ?? []);
        foreach (['merchant_id', 'pos_id', 'crc_key', 'api_key'] as $k) {
            if (empty($cfg[$k])) {
                throw PaymentProviderException::notConfigured($this->id(), $k);
            }
        }

        $crc = (string) $cfg['crc_key'];
        try {
            $crc = Crypt::decryptString($crc);
        } catch (\Throwable) {
            // plain-text fallback (testy)
        }
        $apiKey = (string) $cfg['api_key'];
        try {
            $apiKey = Crypt::decryptString($apiKey);
        } catch (\Throwable) {
        }

        return [
            'merchant_id' => (int) $cfg['merchant_id'],
            'pos_id' => (int) $cfg['pos_id'],
            'crc_key' => $crc,
            'api_key' => $apiKey,
            'sandbox' => (bool) ($cfg['sandbox'] ?? false),
            'force_method' => isset($cfg['force_method']) ? (int) $cfg['force_method'] : null,
        ];
    }

    private function description(Payment $payment, Tenant $tenant): string
    {
        $kind = $payment->calendar_entry_id !== null
            ? 'Lekcja jeździecka'
            : ($payment->pass_id !== null ? 'Karnet' : 'Płatność');

        return $kind.' - '.$tenant->name;
    }
}
