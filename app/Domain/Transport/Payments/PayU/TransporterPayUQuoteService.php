<?php

declare(strict_types=1);

namespace App\Domain\Transport\Payments\PayU;

use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Per-transporter PayU dla ofert transportowych — quote payments via PayU.
 * Patrz docs/TRANSPORT.md §16.
 *
 * KEY DESIGN: Hovera NIE jest tu merchant of record (marketplace model).
 * Credentials (pos_id, oauth_client_id, oauth_client_secret, md5_key)
 * trzymane w `tenants.settings.payments.payu` (encrypted) — analogicznie
 * do P24 quote autopay (`TransporterP24QuoteService`).
 *
 * Różnice względem `App\Services\Billing\PayUService`:
 *   - target jest Quote (transport), nie Invoice/AddonPurchase
 *   - tracking inline na Quote (payu_*) bo Quote nie ma client_id
 *   - return URL ląduje na quote landing page (`public.transport.payu.return`)
 *   - webhook leci do Hovery (`public.transport.payu.webhook`)
 *   - creds per-tenant (zmienne), nie globalne (env vars)
 *
 * PayU API:
 *   - OAuth2 client_credentials → access_token (12h TTL, cached per tenant)
 *   - Create order: POST /api/v2_1/orders → { orderId, redirectUri }
 *   - Webhook signature: SHA256(raw_body + md5_key) hex
 *
 * Reference: https://developers.payu.com/europe/pl/restapi.html
 */
class TransporterPayUQuoteService
{
    public const PROD_HOST = 'https://secure.payu.com';

    public const SANDBOX_HOST = 'https://secure.snd.payu.com';

    /**
     * Per-tenant OAuth cache TTL — 11h (PayU access_token żyje 12h).
     */
    private const TOKEN_TTL_SECONDS = 11 * 3600;

    /**
     * Czy transporter ma wszystkie wymagane fields w settings.
     */
    public function isConfigured(Tenant $tenant): bool
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.payu') ?? []);
        foreach (['pos_id', 'oauth_client_id', 'oauth_client_secret', 'md5_key'] as $k) {
            if (empty($cfg[$k])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rejestruje order w PayU dla quote'u i zwraca hosted checkout URL.
     * Zapisuje order_id + URL na Quote.
     *
     * @throws RuntimeException PayU API error
     * @throws InvalidArgumentException quote / config invalid
     */
    public function createPaymentSession(Tenant $tenant, Quote $quote): string
    {
        if (! $this->isConfigured($tenant)) {
            throw new InvalidArgumentException('Transporter nie ma skonfigurowanego PayU (tenant.settings.payments.payu).');
        }

        // PayU oficjalnie wspiera PLN/EUR/CZK/GBP/USD, ale dla MVP
        // ograniczamy do PLN — fallback do `payment_url` template'a dla
        // walut zagranicznych (decyzja produktowa, łatwa do zmiany).
        if ($quote->currency !== 'PLN') {
            throw new InvalidArgumentException('PayU quote autopay wspiera tylko PLN — quote currency: '.$quote->currency);
        }

        $grossPln = (float) $quote->gross_total;
        if ($grossPln <= 0) {
            throw new InvalidArgumentException('Quote gross_total musi być > 0 dla PayU.');
        }

        $cfg = $this->resolveCredentials($tenant);
        $host = $this->host($cfg);

        $extOrderId = (string) $quote->id;
        $amount = (int) round($grossPln * 100); // grosze

        $payload = [
            'extOrderId' => $extOrderId,
            'merchantPosId' => (string) $cfg['pos_id'],
            'description' => $this->description($quote),
            'currencyCode' => $quote->currency,
            'totalAmount' => (string) $amount,
            'customerIp' => request()->ip() ?: '127.0.0.1',
            'notifyUrl' => route('public.transport.payu.webhook', [
                'tenant_slug' => $tenant->slug,
            ]),
            'continueUrl' => route('public.transport.payu.return', [
                'tenant_slug' => $tenant->slug,
                'quote_id' => $quote->id,
            ]),
            'buyer' => [
                'email' => $quote->customer_email ?: 'no-reply@hovera.app',
                'language' => 'pl',
            ],
            'products' => [[
                'name' => $this->description($quote),
                'unitPrice' => (string) $amount,
                'quantity' => '1',
            ]],
        ];

        $token = $this->getAccessToken($tenant, $cfg);

        $response = Http::withToken($token)
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($host.'/api/v2_1/orders', $payload);

        $status = $response->status();
        if (! in_array($status, [200, 201, 302], true)) {
            $err = (string) data_get($response->json(), 'status.statusDesc', $response->body());
            throw new RuntimeException("PayU quote order failed: {$err} (HTTP {$status})");
        }

        $body = (array) $response->json();
        $orderId = (string) data_get($body, 'orderId', '');
        $redirectUri = (string) data_get($body, 'redirectUri', '');

        if ($redirectUri === '' && $status === 302) {
            $redirectUri = (string) $response->header('Location');
        }

        if ($orderId === '' || $redirectUri === '') {
            throw new RuntimeException('PayU quote order: missing orderId or redirectUri in response.');
        }

        $quote->forceFill([
            'payu_order_id' => $orderId,
            'payu_ext_order_id' => $extOrderId,
            'payu_payment_url' => $redirectUri,
        ])->save();

        return $redirectUri;
    }

    /**
     * Verify webhook signature — analogous do `PayUService::verifyWebhookSignature`,
     * ale używa md5_key transportera z tenant.settings.
     */
    public function verifyWebhook(Tenant $tenant, string $rawBody, string $signatureHeader): bool
    {
        if (! $this->isConfigured($tenant) || $rawBody === '' || $signatureHeader === '') {
            return false;
        }

        $cfg = $this->resolveCredentials($tenant);
        if ($cfg['md5_key'] === '') {
            return false;
        }

        $signature = $this->extractSignature($signatureHeader);
        if ($signature === '') {
            return false;
        }

        $expected = hash('sha256', $rawBody.$cfg['md5_key']);

        return hash_equals($expected, $signature);
    }

    /**
     * Full webhook processing: verify signature, find quote, flip
     * `payu_paid_at` + `payment_completed_at`. Idempotent.
     *
     * PayU `status` enum: PENDING, WAITING_FOR_CONFIRMATION, COMPLETED (flip),
     * CANCELED, REJECTED. Flip tylko dla COMPLETED.
     *
     * @param  array<string,mixed>  $payload
     */
    public function processWebhook(Tenant $tenant, array $payload, string $signatureHeader, string $rawBody): bool
    {
        if (! $this->verifyWebhook($tenant, $rawBody, $signatureHeader)) {
            Log::warning('PayU quote webhook: signature mismatch', [
                'tenant' => $tenant->slug,
            ]);

            return false;
        }

        $order = (array) ($payload['order'] ?? []);
        $orderId = (string) ($order['orderId'] ?? '');
        $status = (string) ($order['status'] ?? '');
        $totalAmount = (int) ($order['totalAmount'] ?? 0);

        if ($orderId === '') {
            Log::warning('PayU quote webhook: missing orderId', [
                'tenant' => $tenant->slug,
            ]);

            return false;
        }

        if ($status !== 'COMPLETED') {
            // Ack ale brak flipu (PENDING/WAITING/REJECTED — nieterminalne).
            return true;
        }

        $quote = Quote::query()->where('payu_order_id', $orderId)->first();
        if ($quote === null) {
            Log::warning('PayU quote webhook: quote not found', [
                'tenant' => $tenant->slug,
                'order_id' => $orderId,
            ]);

            return false;
        }

        if ($quote->payu_paid_at !== null) {
            return true; // idempotent
        }

        $expectedAmount = (int) round((float) $quote->gross_total * 100);
        if ($totalAmount !== $expectedAmount) {
            Log::warning('PayU quote webhook: amount mismatch', [
                'tenant' => $tenant->slug,
                'order_id' => $orderId,
                'expected' => $expectedAmount,
                'got' => $totalAmount,
            ]);

            return false;
        }

        $quote->forceFill([
            'payu_paid_at' => now(),
            // Auto-flip payment_completed_at gdy webhook first (PayU jako
            // źródło prawdy). Nie nadpisujemy ręcznego oznaczenia.
            'payment_completed_at' => $quote->payment_completed_at ?? now(),
            'payment_method_label' => $quote->payment_method_label ?: 'PayU',
        ])->save();

        return true;
    }

    /**
     * Pobiera OAuth access_token z cache lub PayU. Cache key zawiera
     * tenant_id żeby nie mieszać tokenów między transporterami.
     *
     * @param  array{pos_id:int, oauth_client_id:string, oauth_client_secret:string, sandbox:bool}  $cfg
     */
    private function getAccessToken(Tenant $tenant, array $cfg): string
    {
        $cacheKey = $this->tokenCacheKey($tenant, $cfg);

        $token = Cache::get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post($this->host($cfg).'/pl/standard/user/oauth/authorize', [
                'grant_type' => 'client_credentials',
                'client_id' => $cfg['oauth_client_id'],
                'client_secret' => $cfg['oauth_client_secret'],
            ]);

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'error_description', $response->body());
            throw new RuntimeException("PayU OAuth failed for tenant {$tenant->slug}: {$err} (HTTP {$response->status()})");
        }

        $token = (string) data_get($response->json(), 'access_token', '');
        if ($token === '') {
            throw new RuntimeException("PayU OAuth: empty access_token for tenant {$tenant->slug}.");
        }

        Cache::put($cacheKey, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @param  array{sandbox:bool, pos_id:int}  $cfg
     */
    private function tokenCacheKey(Tenant $tenant, array $cfg): string
    {
        $env = $cfg['sandbox'] ? 'sandbox' : 'production';

        return 'payu.access_token.transporter.'.$tenant->id.'.'.$env.'.'.$cfg['pos_id'];
    }

    /**
     * Wyciąga `signature=...` z PayU header'a w formacie key=val;key=val.
     */
    private function extractSignature(string $header): string
    {
        foreach (explode(';', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2 && strtolower(trim($kv[0])) === 'signature') {
                return trim($kv[1]);
            }
        }

        return '';
    }

    /**
     * @return array{pos_id:int, oauth_client_id:string, oauth_client_secret:string, md5_key:string, sandbox:bool}
     */
    private function resolveCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.payu') ?? []);

        // Encryption: secret + md5_key + client_secret są szyfrowane via
        // Crypt::encryptString przed zapisaniem w settings (mirror P24).
        $oauthClientSecret = $this->maybeDecrypt((string) ($cfg['oauth_client_secret'] ?? ''));
        $md5Key = $this->maybeDecrypt((string) ($cfg['md5_key'] ?? ''));

        return [
            'pos_id' => (int) ($cfg['pos_id'] ?? 0),
            'oauth_client_id' => (string) ($cfg['oauth_client_id'] ?? ''),
            'oauth_client_secret' => $oauthClientSecret,
            'md5_key' => $md5Key,
            'sandbox' => (bool) ($cfg['sandbox'] ?? false),
        ];
    }

    private function maybeDecrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // Plain-text fallback (testy z plain creds, ewentualnie legacy zapisy).
            return $value;
        }
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
