<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayU REST API integration. Trzy-krokowy flow:
 *
 *   1. OAuth2 client_credentials → access_token (cachowany 1h per stajnia)
 *   2. POST /api/v2_1/orders z Bearer → orderId + redirectUri
 *   3. PayU async POST do /payments/payu/webhook z OpenPayU-Signature header
 *      → MD5 sign verify, status mapping
 *
 * Required tenant credentials (settings.payments.payu):
 *   pos_id           merchantPosId, np. 145227
 *   client_id        OAuth2 client_id
 *   client_secret    OAuth2 client_secret             (encrypted)
 *   md5_key          tzw. "drugi klucz", do verify    (encrypted)
 *   sandbox          bool
 *   force_method     opcjonalnie wymuszona metoda (np. 'blik', 'c', kod banku)
 *
 * Reference: https://developers.payu.com/europe/docs/getting-started/
 */
class PayUPaymentProvider implements PaymentProviderInterface
{
    public const PROD_HOST = 'https://secure.payu.com';

    public const SANDBOX_HOST = 'https://secure.snd.payu.com';

    /**
     * Najczęściej forsowane metody PayU. Pusta = pełna lista u PayU.
     *
     * @var array<string,string>
     */
    public const METHOD_LABELS = [
        'blik' => 'BLIK',
        'c' => 'Karty (Visa/Mastercard)',
        'jp' => 'PKO BP iPKO',
        'm' => 'mBank mTransfer',
        'i' => 'Inteligo',
        'pkk' => 'Pekao24',
        'wm' => 'WBK / Santander',
        'ing' => 'ING Bank Śląski',
        'ap' => 'Alior Online',
    ];

    /**
     * @return array<string,string>
     */
    public static function methodOptions(): array
    {
        return self::METHOD_LABELS;
    }

    public function id(): string
    {
        return 'payu';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $cfg = $this->resolveCredentials($tenant);
        $host = $cfg['sandbox'] ? self::SANDBOX_HOST : self::PROD_HOST;

        $token = $this->oauthToken($tenant, $cfg, $host);

        $payload = [
            'extOrderId' => $payment->id,
            'notifyUrl' => route('public.payments.webhook', [
                'slug' => $tenant->slug,
                'provider' => 'payu',
            ]),
            'continueUrl' => route('public.payments.return', [
                'slug' => $tenant->slug,
                'provider' => 'payu',
                'payment' => $payment->id,
            ]),
            'customerIp' => request()->ip() ?? '127.0.0.1',
            'merchantPosId' => (string) $cfg['pos_id'],
            'description' => $this->description($payment, $tenant),
            'currencyCode' => $payment->currency,
            'totalAmount' => (string) $payment->amount_cents,
            'buyer' => $this->buyer($payment),
            'products' => [
                [
                    'name' => $this->description($payment, $tenant),
                    'unitPrice' => (string) $payment->amount_cents,
                    'quantity' => '1',
                ],
            ],
        ];

        if (! empty($cfg['force_method'])) {
            $payload['payMethods'] = [
                'payMethod' => [
                    'type' => 'PBL',
                    'value' => (string) $cfg['force_method'],
                ],
            ];
        }

        $response = Http::withToken($token)
            ->withOptions(['allow_redirects' => false]) // PayU 302 z redirectUri w Location/body
            ->acceptJson()
            ->asJson()
            ->post($host.'/api/v2_1/orders', $payload);

        // PayU returns 302 with redirectUri+orderId in body when allow_redirects=false
        if (! in_array($response->status(), [200, 201, 302], true)) {
            $err = (string) data_get($response->json(), 'status.statusDesc', 'unknown error');
            throw PaymentProviderException::apiError($this->id(), $err);
        }

        $body = $response->json() ?: [];
        $statusCode = (string) data_get($body, 'status.statusCode', '');
        $redirectUri = (string) data_get($body, 'redirectUri', '');
        $orderId = (string) data_get($body, 'orderId', '');

        if ($statusCode !== 'SUCCESS' || $redirectUri === '' || $orderId === '') {
            throw PaymentProviderException::apiError($this->id(), 'invalid response: '.$statusCode);
        }

        $payment->forceFill([
            'provider_ref' => $orderId,
            'checkout_url' => $redirectUri,
            'status' => PaymentStatus::Processing->value,
            'expires_at' => now()->addHours(24),
            'provider_data' => ['order_response' => $body, 'host' => $host],
        ])->save();

        return $redirectUri;
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    /**
     * PayU notification: JSON body with order data, OpenPayU-Signature header
     * format: `sender=...;signature=<md5hex>;algorithm=MD5;content=DOCUMENT`
     *
     * Signature = MD5(body + md5_key) — czysto, body raw bytes (UTF-8).
     */
    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        $cfg = $this->resolveCredentials($tenant);

        $rawBody = $request->getContent();
        $sigHeader = (string) $request->header('OpenPayU-Signature', '');

        if (! $this->verifySignature($rawBody, $sigHeader, $cfg['md5_key'])) {
            Log::warning('PayU webhook: invalid signature', ['tenant' => $tenant->slug]);

            return response('invalid signature', 400);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response('invalid body', 400);
        }

        $order = (array) ($payload['order'] ?? []);
        $orderId = (string) ($order['orderId'] ?? '');
        $extOrderId = (string) ($order['extOrderId'] ?? '');
        $statusRaw = (string) ($order['status'] ?? '');

        // extOrderId = our payment id; fallback to orderId match
        $payment = null;
        if ($extOrderId !== '') {
            $payment = Payment::query()->where('id', $extOrderId)->first();
        }
        if (! $payment && $orderId !== '') {
            $payment = Payment::query()->where('provider_ref', $orderId)->first();
        }
        if (! $payment) {
            return response('not_found', 404);
        }

        $newStatus = $this->mapStatus($statusRaw);
        if ($newStatus === null) {
            // PENDING / WAITING_FOR_CONFIRMATION → wciąż w toku
            return response('ok', 200);
        }
        if ($payment->status->isTerminal() && $payment->status !== $newStatus) {
            return response('ok', 200);
        }

        $payment->forceFill([
            'status' => $newStatus->value,
            'paid_at' => $newStatus === PaymentStatus::Succeeded ? now() : $payment->paid_at,
            'provider_data' => array_merge((array) $payment->provider_data, [
                'last_webhook' => $payload,
                'order_id' => $orderId,
            ]),
        ])->save();

        return response('ok', 200);
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        $cfg = $this->resolveCredentials($tenant);
        $host = $cfg['sandbox'] ? self::SANDBOX_HOST : self::PROD_HOST;
        $token = $this->oauthToken($tenant, $cfg, $host);

        $orderId = (string) $payment->provider_ref;
        if ($orderId === '') {
            throw PaymentProviderException::apiError($this->id(), 'brak orderId');
        }

        $resp = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($host.'/api/v2_1/orders/'.$orderId.'/refunds', [
                'refund' => [
                    'description' => 'Hovera refund: '.$payment->id,
                    'amount' => (string) $payment->amount_cents,
                ],
            ]);

        if (! $resp->successful()) {
            throw PaymentProviderException::apiError($this->id(), 'refund failed');
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded->value,
            'refunded_at' => now(),
        ])->save();
    }

    /**
     * Cache OAuth2 token na 50 min (tokeny PayU żyją 1h, mamy bufor).
     */
    private function oauthToken(Tenant $tenant, array $cfg, string $host): string
    {
        $cacheKey = "payu_token:{$tenant->id}";

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($cfg, $host) {
            $resp = Http::asForm()
                ->post($host.'/pl/standard/user/oauth/authorize', [
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) $cfg['client_id'],
                    'client_secret' => (string) $cfg['client_secret'],
                ]);

            if (! $resp->successful()) {
                throw PaymentProviderException::apiError($this->id(), 'OAuth2 failed: '.$resp->status());
            }

            $token = (string) data_get($resp->json(), 'access_token', '');
            if ($token === '') {
                throw PaymentProviderException::apiError($this->id(), 'puste access_token');
            }

            return $token;
        });
    }

    private function verifySignature(string $rawBody, string $headerValue, string $md5Key): bool
    {
        if ($headerValue === '' || $md5Key === '') {
            return false;
        }

        $signature = '';
        foreach (explode(';', $headerValue) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if (strtolower($k) === 'signature') {
                $signature = $v;
            }
        }
        if ($signature === '') {
            return false;
        }

        $expected = md5($rawBody.$md5Key);

        return hash_equals($expected, $signature);
    }

    /**
     * @see https://developers.payu.com/europe/docs/checkout-1/order-statuses
     */
    private function mapStatus(string $status): ?PaymentStatus
    {
        return match ($status) {
            'COMPLETED' => PaymentStatus::Succeeded,
            'CANCELED', 'REJECTED' => PaymentStatus::Failed,
            // PENDING / WAITING_FOR_CONFIRMATION / NEW → wciąż otwarte
            default => null,
        };
    }

    /**
     * @return array{
     *   pos_id:int, client_id:string, client_secret:string,
     *   md5_key:string, sandbox:bool, force_method:?string
     * }
     */
    private function resolveCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.payu') ?? []);
        foreach (['pos_id', 'client_id', 'client_secret', 'md5_key'] as $k) {
            if (empty($cfg[$k])) {
                throw PaymentProviderException::notConfigured($this->id(), $k);
            }
        }

        $secret = (string) $cfg['client_secret'];
        try {
            $secret = Crypt::decryptString($secret);
        } catch (\Throwable) {
        }
        $md5 = (string) $cfg['md5_key'];
        try {
            $md5 = Crypt::decryptString($md5);
        } catch (\Throwable) {
        }

        return [
            'pos_id' => (int) $cfg['pos_id'],
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => $secret,
            'md5_key' => $md5,
            'sandbox' => (bool) ($cfg['sandbox'] ?? false),
            'force_method' => isset($cfg['force_method']) && $cfg['force_method'] !== ''
                ? (string) $cfg['force_method']
                : null,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function buyer(Payment $payment): array
    {
        $client = $payment->client;

        return [
            'email' => (string) ($client?->email ?? 'no-reply@hovera.app'),
            'firstName' => (string) ($client?->name ?? 'Klient'),
            'language' => 'pl',
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
