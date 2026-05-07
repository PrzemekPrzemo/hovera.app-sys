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
 * Stripe Checkout integration via REST API (no Stripe-PHP SDK needed).
 * Stripe's API is stable and the surface we use is small — Laravel's
 * Http client + manual webhook signature verification are sufficient.
 *
 * Required tenant credentials (settings.payments.stripe):
 *   secret_key       sk_live_... or sk_test_...   (encrypted at rest)
 *   webhook_secret   whsec_...                    (encrypted at rest)
 *   publishable_key  pk_live_...                  (optional, for client widget)
 *   enabled_methods  ["card", "blik", "p24"]      (which methods to expose)
 *
 * Flow:
 *   1. POST /v1/checkout/sessions → returns {id, url}
 *   2. Persist id as provider_ref, url as checkout_url
 *   3. Redirect user to url
 *   4. Stripe → /payments/stripe/webhook with checkout.session.completed
 *   5. We verify Stripe-Signature header (HMAC-SHA256 over t={ts}.{body})
 *   6. Mark payment Succeeded
 */
class StripePaymentProvider implements PaymentProviderInterface
{
    public const API_BASE = 'https://api.stripe.com/v1';

    /**
     * Methods Stripe Checkout supports for our region. The owner picks
     * a subset in tenant settings; if empty, we default to ['card'].
     *
     * Note: 'p24' is Stripe's term for Przelewy24 (no relation to our
     * own P24Provider — Stripe routes through Przelewy24 themselves).
     *
     * @var array<int,string>
     */
    public const SUPPORTED_METHODS = [
        'card', 'blik', 'p24', 'bancontact', 'ideal', 'eps', 'giropay',
        'sepa_debit', 'sofort',
    ];

    /**
     * Polish labels for UI display in PaymentSettings page.
     *
     * @return array<string,string>
     */
    public static function methodOptions(): array
    {
        return [
            'card' => 'Karty (Visa / Mastercard / American Express)',
            'blik' => 'BLIK',
            'p24' => 'Przelewy24',
            'bancontact' => 'Bancontact (BE)',
            'ideal' => 'iDEAL (NL)',
            'eps' => 'EPS (AT)',
            'giropay' => 'Giropay (DE)',
            'sepa_debit' => 'SEPA Direct Debit',
            'sofort' => 'Sofort',
        ];
    }

    public function id(): string
    {
        return 'stripe';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $cfg = $this->resolveCredentials($tenant);

        $methods = $this->enabledMethods($tenant);
        $successUrl = route('public.payments.return', [
            'slug' => $tenant->slug,
            'provider' => 'stripe',
            'payment' => $payment->id,
        ]).'?status=success';
        $cancelUrl = route('public.payments.return', [
            'slug' => $tenant->slug,
            'provider' => 'stripe',
            'payment' => $payment->id,
        ]).'?status=cancel';

        $payload = [
            'mode' => 'payment',
            'payment_method_types' => $methods,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $payment->id,
            'metadata' => [
                'hovera_payment_id' => $payment->id,
                'hovera_tenant_slug' => $tenant->slug,
            ],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($payment->currency),
                        'unit_amount' => $payment->amount_cents,
                        'product_data' => [
                            'name' => $this->lineItemName($payment, $tenant),
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withBasicAuth($cfg['secret_key'], '')
            ->asForm()
            ->post(self::API_BASE.'/checkout/sessions', $this->flatten($payload));

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'error.message', 'unknown error');
            throw PaymentProviderException::apiError($this->id(), $err);
        }

        $session = $response->json();
        $url = (string) data_get($session, 'url', '');
        $sessionId = (string) data_get($session, 'id', '');

        if ($url === '' || $sessionId === '') {
            throw PaymentProviderException::apiError($this->id(), 'pusty payload odpowiedzi');
        }

        $payment->forceFill([
            'provider_ref' => $sessionId,
            'checkout_url' => $url,
            'status' => PaymentStatus::Processing->value,
            'expires_at' => now()->addHours(24),
            'provider_data' => ['session' => $session],
        ])->save();

        return $url;
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        // Status z webhooka jest źródłem prawdy. Tu tylko wracamy info-page.
        return response('OK', 200);
    }

    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        $cfg = $this->resolveCredentials($tenant);

        $rawBody = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if (! $this->verifySignature($rawBody, $signature, $cfg['webhook_secret'])) {
            Log::warning('Stripe webhook: invalid signature', ['tenant' => $tenant->slug]);

            return response('invalid signature', 400);
        }

        $event = json_decode($rawBody, true);
        if (! is_array($event)) {
            return response('invalid body', 400);
        }

        $type = (string) ($event['type'] ?? '');
        $obj = (array) data_get($event, 'data.object', []);

        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->markByRef((string) ($obj['id'] ?? ''), PaymentStatus::Succeeded);
                break;

            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                $this->markByRef((string) ($obj['id'] ?? ''), PaymentStatus::Failed);
                break;

            case 'charge.refunded':
                // Charge events reference payment_intent — Hovera's id lives in
                // the session metadata, so we look up by metadata fallback.
                $hoveraId = (string) data_get($obj, 'metadata.hovera_payment_id', '');
                if ($hoveraId !== '') {
                    $payment = Payment::query()->find($hoveraId);
                    if ($payment) {
                        $payment->forceFill([
                            'status' => PaymentStatus::Refunded->value,
                            'refunded_at' => now(),
                        ])->save();
                    }
                }
                break;
        }

        return response('ok', 200);
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        $cfg = $this->resolveCredentials($tenant);

        // Need the payment_intent id from session — fetch session detail
        $sessionId = (string) $payment->provider_ref;
        if ($sessionId === '') {
            throw PaymentProviderException::apiError($this->id(), 'brak provider_ref na płatności');
        }

        $sessionResp = Http::withBasicAuth($cfg['secret_key'], '')
            ->get(self::API_BASE.'/checkout/sessions/'.$sessionId);

        if (! $sessionResp->successful()) {
            throw PaymentProviderException::apiError($this->id(), 'fetch session failed');
        }
        $paymentIntent = (string) data_get($sessionResp->json(), 'payment_intent', '');
        if ($paymentIntent === '') {
            throw PaymentProviderException::apiError($this->id(), 'brak payment_intent na sesji');
        }

        $resp = Http::withBasicAuth($cfg['secret_key'], '')
            ->asForm()
            ->post(self::API_BASE.'/refunds', [
                'payment_intent' => $paymentIntent,
                'metadata' => ['hovera_payment_id' => $payment->id],
            ]);

        if (! $resp->successful()) {
            $err = (string) data_get($resp->json(), 'error.message', 'unknown');
            throw PaymentProviderException::apiError($this->id(), $err);
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded->value,
            'refunded_at' => now(),
        ])->save();
    }

    /**
     * Stripe signature: header format `t=<unix_ts>,v1=<hex_hmac>` (multiple
     * v1s allowed when the secret has been rotated). Body that's signed is
     * `<unix_ts>.<raw_body>`. We accept signatures up to 5 minutes old.
     */
    private function verifySignature(string $rawBody, string $headerValue, string $secret): bool
    {
        if ($headerValue === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $headerValue) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = (int) $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        foreach ($signatures as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }

        return false;
    }

    private function markByRef(string $ref, PaymentStatus $newStatus): void
    {
        if ($ref === '') {
            return;
        }
        $payment = Payment::query()->where('provider_ref', $ref)->first();
        if (! $payment) {
            return;
        }

        // Idempotency — webhook może lecieć kilka razy. Nie cofamy z terminala.
        if ($payment->status->isTerminal() && $payment->status !== $newStatus) {
            return;
        }

        $payment->forceFill([
            'status' => $newStatus->value,
            'paid_at' => $newStatus === PaymentStatus::Succeeded ? now() : $payment->paid_at,
        ])->save();
    }

    /**
     * @return array{secret_key:string, webhook_secret:string, publishable_key?:string}
     */
    private function resolveCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.stripe') ?? []);
        $out = [];
        foreach (['secret_key', 'webhook_secret'] as $k) {
            $val = (string) ($cfg[$k] ?? '');
            if ($val === '') {
                throw PaymentProviderException::notConfigured($this->id(), $k);
            }
            // Wartość była zaszyfrowana przy zapisie w PaymentSettings
            try {
                $out[$k] = Crypt::decryptString($val);
            } catch (\Throwable) {
                // Plain-text fallback (np. testy) — Crypt rzuca gdy to nie jest jego payload.
                $out[$k] = $val;
            }
        }
        if (! empty($cfg['publishable_key'])) {
            $out['publishable_key'] = (string) $cfg['publishable_key'];
        }

        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function enabledMethods(Tenant $tenant): array
    {
        $methods = (array) (data_get($tenant->settings, 'payments.stripe.enabled_methods') ?? []);
        $methods = array_values(array_intersect($methods, self::SUPPORTED_METHODS));

        return $methods === [] ? ['card'] : $methods;
    }

    private function lineItemName(Payment $payment, Tenant $tenant): string
    {
        $kind = $payment->calendar_entry_id !== null
            ? 'Lekcja jeździecka'
            : ($payment->pass_id !== null ? 'Karnet' : 'Płatność');

        return $kind.' — '.$tenant->name;
    }

    /**
     * Stripe expects PHP-array-style keys flattened to bracket notation
     * for form-encoded payloads. e.g. `line_items[0][price_data][currency]`.
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $out = [];
        foreach ($payload as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}[{$k}]";
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
            }
        }

        return $out;
    }
}
