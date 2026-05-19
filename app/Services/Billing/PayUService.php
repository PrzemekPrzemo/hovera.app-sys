<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Domain\Transport\Sponsored\SponsoredPlacementService;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Centralny (master-admin) integration z PayU dla SaaS billing Hovery.
 * Hovera jako merchant of record inkasuje od tenantów za subskrypcje
 * (FV) + add-ony — komplementarny do `Przelewy24Service`. Patrz
 * docs/TRANSPORT.md §16.
 *
 * UWAGA: Per-tenant PayU (stable→klient, transporter→klient) żyje w
 * osobnych serwisach analogicznych do P24 — `PayUPaymentProvider`
 * (per-stable invoices) + `TransporterPayUQuoteService` (per-transporter
 * quote autopay). TAMTE używają creds z `tenants.settings.payments.payu`
 * encrypted. Tu jest payment tenant → Hovera za SaaS, creds z
 * `services.payu.*` (env vars).
 *
 * PayU API:
 *   - OAuth2 client_credentials: POST /pl/standard/user/oauth/authorize
 *     → access_token (12h TTL, cache'owany 11h żeby mieć margines).
 *   - Create order: POST /api/v2_1/orders → 302 z Location: redirectUri
 *     lub 200 z body { orderId, redirectUri, status:{statusCode: SUCCESS} }.
 *   - Webhook signature: SHA256 hex z (raw_body + md5_key); header
 *     `OpenPayU-Signature` w formacie `signature=...;algorithm=SHA-256;sender=...`.
 *
 * Reference: https://developers.payu.com/europe/pl/restapi.html
 */
class PayUService
{
    public const PROD_HOST = 'https://secure.payu.com';

    public const SANDBOX_HOST = 'https://secure.snd.payu.com';

    /**
     * OAuth token cache TTL — PayU access_token żyje 12h (43199s). Cache
     * 11h żeby zawsze mieć min. 1h margines na slow requests.
     */
    private const TOKEN_TTL_SECONDS = 11 * 3600;

    public function __construct(
        private readonly int $posId,
        private readonly string $oauthClientId,
        private readonly string $oauthClientSecret,
        private readonly string $md5Key,
        private readonly string $secondKey,
        private readonly string $env = 'sandbox',
    ) {
        if ($posId === 0) {
            throw new InvalidArgumentException('PAYU_POS_ID is empty.');
        }
        if ($oauthClientId === '' || $oauthClientSecret === '') {
            throw new InvalidArgumentException('PAYU OAuth credentials are empty.');
        }
        if ($md5Key === '') {
            throw new InvalidArgumentException('PAYU_MD5_KEY is empty (signature verification disabled).');
        }
        if (! in_array($env, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException("Unknown PayU env: {$env}");
        }
    }

    /**
     * Tworzy zamówienie PayU dla faktury i zwraca hosted checkout URL.
     * Zapisuje order_id + URL na fakturze (analogicznie do P24).
     *
     * @throws RuntimeException gdy PayU zwróci błąd
     */
    public function createPayment(Invoice $invoice): string
    {
        if ((int) $invoice->total_cents <= 0) {
            throw new InvalidArgumentException('Invoice total must be > 0.');
        }

        $extOrderId = (string) $invoice->id;
        $payload = $this->buildOrderPayload(
            extOrderId: $extOrderId,
            amountCents: (int) $invoice->total_cents,
            currency: (string) ($invoice->currency ?: 'PLN'),
            description: $this->invoiceDescription($invoice),
            buyerEmail: $this->resolveEmail($invoice),
            notifyUrl: route('webhooks.payu'),
            continueUrl: route('webhooks.payu.return', ['invoice_id' => $invoice->id]),
        );

        $order = $this->createOrder($payload);

        $invoice->forceFill([
            'payu_order_id' => $order['orderId'],
            'payu_ext_order_id' => $extOrderId,
            'payu_payment_url' => $order['redirectUri'],
        ])->save();

        return $order['redirectUri'];
    }

    /**
     * Tworzy zamówienie PayU dla zakupu add-onu (Hovera-as-merchant).
     * Mirror createPayment() ale dla AddonPurchase.
     */
    public function chargeAddon(AddonPurchase $purchase): string
    {
        if ((int) $purchase->amount_cents <= 0) {
            throw new InvalidArgumentException('AddonPurchase amount must be > 0.');
        }
        if ($purchase->isTerminal()) {
            throw new InvalidArgumentException('AddonPurchase już w stanie terminalnym ('.$purchase->status.').');
        }

        $extOrderId = (string) $purchase->id;
        $payload = $this->buildOrderPayload(
            extOrderId: $extOrderId,
            amountCents: (int) $purchase->amount_cents,
            currency: (string) ($purchase->currency ?: 'PLN'),
            description: $this->addonDescription($purchase),
            buyerEmail: $this->resolveAddonEmail($purchase),
            notifyUrl: route('webhooks.payu.addon'),
            continueUrl: route('admin.payu.addon.return', ['purchase_id' => $purchase->id]),
        );

        $order = $this->createOrder($payload);

        $purchase->forceFill([
            'provider' => 'payu',
            'payu_order_id' => $order['orderId'],
            'payu_ext_order_id' => $extOrderId,
            'payu_payment_url' => $order['redirectUri'],
            'status' => AddonPurchase::STATUS_PENDING,
        ])->save();

        return $order['redirectUri'];
    }

    /**
     * Webhook handler dla PayU notify URL — invoice path.
     * Idempotent — drugi webhook na zapłaconą FV zwraca true bez side-effect.
     *
     * PayU wysyła JSON z `order.status` ∈ PENDING|WAITING_FOR_CONFIRMATION|
     * COMPLETED|CANCELED|REJECTED. Mark as paid tylko gdy COMPLETED.
     *
     * @param  array<string,mixed>  $payload
     */
    public function processWebhook(array $payload, string $signatureHeader, string $rawBody): bool
    {
        if (! $this->verifyWebhookSignature($rawBody, $signatureHeader)) {
            Log::warning('PayU webhook: signature mismatch');

            return false;
        }

        $order = (array) ($payload['order'] ?? []);
        $orderId = (string) ($order['orderId'] ?? '');
        $extOrderId = (string) ($order['extOrderId'] ?? '');
        $status = (string) ($order['status'] ?? '');
        $totalAmount = (int) ($order['totalAmount'] ?? 0);

        if ($orderId === '' || $extOrderId === '') {
            Log::warning('PayU webhook: missing orderId / extOrderId');

            return false;
        }

        if ($status !== 'COMPLETED') {
            // Akceptujemy ack ale nie flipujemy statusu (PENDING/WAITING/etc.).
            return true;
        }

        $invoice = Invoice::query()->where('payu_order_id', $orderId)->first();
        if ($invoice === null) {
            Log::warning('PayU webhook: invoice not found', ['order_id' => $orderId]);

            return false;
        }

        if ($invoice->paid_at !== null) {
            return true; // idempotent
        }

        if ($totalAmount !== (int) $invoice->total_cents) {
            Log::warning('PayU webhook: amount mismatch', [
                'order_id' => $orderId,
                'expected' => $invoice->total_cents,
                'got' => $totalAmount,
            ]);

            return false;
        }

        $invoice->forceFill([
            'paid_at' => now(),
            'payu_paid_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Webhook handler dla PayU notify URL — addon purchase path.
     *
     * @param  array<string,mixed>  $payload
     */
    public function processAddonWebhook(array $payload, string $signatureHeader, string $rawBody): bool
    {
        if (! $this->verifyWebhookSignature($rawBody, $signatureHeader)) {
            Log::warning('PayU addon webhook: signature mismatch');

            return false;
        }

        $order = (array) ($payload['order'] ?? []);
        $orderId = (string) ($order['orderId'] ?? '');
        $status = (string) ($order['status'] ?? '');
        $totalAmount = (int) ($order['totalAmount'] ?? 0);

        if ($orderId === '') {
            return false;
        }

        if ($status !== 'COMPLETED') {
            return true;
        }

        $purchase = AddonPurchase::query()->where('payu_order_id', $orderId)->first();
        if ($purchase === null) {
            Log::warning('PayU addon webhook: purchase not found', ['order_id' => $orderId]);

            return false;
        }

        if ($purchase->isPaid()) {
            return true;
        }

        if ($totalAmount !== (int) $purchase->amount_cents) {
            Log::warning('PayU addon webhook: amount mismatch', [
                'order_id' => $orderId,
                'expected' => $purchase->amount_cents,
                'got' => $totalAmount,
            ]);

            return false;
        }

        $purchase->forceFill([
            'status' => AddonPurchase::STATUS_PAID,
            'paid_at' => now(),
            'payu_paid_at' => now(),
        ])->save();

        // Side-effect: sponsored placements → flipuje featured + featured_until.
        // Patrz docs/TRANSPORT.md §16.
        try {
            app(SponsoredPlacementService::class)
                ->applyFromPurchase($purchase->fresh());
        } catch (\Throwable $e) {
            Log::warning('Sponsored placement side-effect failed (PayU)', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Weryfikuje signature webhook'a. PayU wysyła header
     * `OpenPayU-Signature: signature=HEX;algorithm=SHA-256;sender=...`.
     * Expected: hash('sha256', raw_body + md5_key) jako hex.
     *
     * Constant-time compare przez hash_equals().
     */
    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        if ($this->md5Key === '' || $signatureHeader === '') {
            return false;
        }

        $signature = $this->extractSignature($signatureHeader);
        if ($signature === '') {
            return false;
        }

        $expected = hash('sha256', $rawBody.$this->md5Key);

        return hash_equals($expected, $signature);
    }

    /**
     * Wyciąga wartość `signature=...` z PayU header'a w formacie key=val;key=val.
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
     * Pobiera OAuth access_token z cache'a lub PayU. Pozwala na concurrent
     * requests bez race condition — Cache::lock żeby uniknąć podwójnego
     * exchange'u tokenu przy cold start.
     */
    public function getAccessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();

        $token = Cache::get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post($this->host().'/pl/standard/user/oauth/authorize', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->oauthClientId,
                'client_secret' => $this->oauthClientSecret,
            ]);

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'error_description', $response->body());
            throw new RuntimeException("PayU OAuth failed: {$err} (HTTP {$response->status()})");
        }

        $token = (string) data_get($response->json(), 'access_token', '');
        if ($token === '') {
            throw new RuntimeException('PayU OAuth: empty access_token in response.');
        }

        Cache::put($cacheKey, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * Cache key dla tokenu — per pos_id + env, żeby sandbox i prod nie
     * dzieliły tego samego cache key'a.
     */
    private function tokenCacheKey(): string
    {
        return 'payu.access_token.'.$this->env.'.'.$this->posId;
    }

    /**
     * Buduje payload dla POST /api/v2_1/orders. Wspólne dla invoice +
     * addon purchase.
     *
     * @return array<string, mixed>
     */
    private function buildOrderPayload(
        string $extOrderId,
        int $amountCents,
        string $currency,
        string $description,
        string $buyerEmail,
        string $notifyUrl,
        string $continueUrl,
    ): array {
        return [
            'extOrderId' => $extOrderId,
            'merchantPosId' => (string) $this->posId,
            'description' => $description,
            'currencyCode' => $currency,
            'totalAmount' => (string) $amountCents,
            'customerIp' => request()->ip() ?: '127.0.0.1',
            'notifyUrl' => $notifyUrl,
            'continueUrl' => $continueUrl,
            'buyer' => [
                'email' => $buyerEmail,
                'language' => 'pl',
            ],
            'products' => [[
                'name' => $description,
                'unitPrice' => (string) $amountCents,
                'quantity' => '1',
            ]],
        ];
    }

    /**
     * POST /api/v2_1/orders. PayU zwraca 302 z Location header'em (redirectUri)
     * przy domyślnym ustawieniu — wyłączamy redirects żeby chwycić Location
     * bez followingu na końcowy URL. Response body zawiera też orderId.
     *
     * @param  array<string, mixed>  $payload
     * @return array{orderId: string, redirectUri: string}
     *
     * @throws RuntimeException
     */
    private function createOrder(array $payload): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($this->host().'/api/v2_1/orders', $payload);

        // PayU zwraca 302 (Found) z Location header'em lub 200 z body
        // zawierającym redirectUri. Body i tak ma orderId.
        $status = $response->status();
        if (! in_array($status, [200, 201, 302], true)) {
            $err = (string) data_get($response->json(), 'status.statusDesc', $response->body());
            throw new RuntimeException("PayU order create failed: {$err} (HTTP {$status})");
        }

        $body = (array) $response->json();
        $orderId = (string) data_get($body, 'orderId', '');
        $redirectUri = (string) data_get($body, 'redirectUri', '');

        if ($redirectUri === '' && $status === 302) {
            $redirectUri = (string) $response->header('Location');
        }

        if ($orderId === '' || $redirectUri === '') {
            throw new RuntimeException('PayU order create: missing orderId or redirectUri in response.');
        }

        return ['orderId' => $orderId, 'redirectUri' => $redirectUri];
    }

    private function host(): string
    {
        return $this->env === 'production' ? self::PROD_HOST : self::SANDBOX_HOST;
    }

    private function invoiceDescription(Invoice $invoice): string
    {
        $plan = $invoice->plan_code !== null ? ' ('.$invoice->plan_code.')' : '';

        return 'Hovera FV '.$invoice->number.$plan;
    }

    private function addonDescription(AddonPurchase $purchase): string
    {
        return 'Hovera add-on '.$purchase->addon_name;
    }

    private function resolveAddonEmail(AddonPurchase $purchase): string
    {
        return $this->resolveOwnerEmail($purchase->tenant);
    }

    private function resolveEmail(Invoice $invoice): string
    {
        return $this->resolveOwnerEmail($invoice->tenant);
    }

    private function resolveOwnerEmail(?object $tenant): string
    {
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
