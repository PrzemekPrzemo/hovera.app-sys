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
 * Mollie integration via REST API. Świetny dla EU stables — jeden klucz
 * pokrywa: creditcard, BLIK (PL), iDEAL (NL), Bancontact (BE), Giropay (DE),
 * SEPA + Apple/Google Pay, PayPal.
 *
 * Mollie webhook contract: payload zawiera tylko `id`. Status pobiera się
 * przez GET /v2/payments/{id} — to BARDZO utrudnia spoofing webhooków
 * (atakujący musiałby też przejąć Mollie API).
 *
 * Required tenant credentials (settings.payments.mollie):
 *   api_key          live_xxx... lub test_xxx...   (encrypted)
 *   enabled_methods  ["ideal","creditcard","blik"] (opcjonalne)
 *
 * Flow:
 *   1. POST /v2/payments → returns {id, _links.checkout.href}
 *   2. Persist id jako provider_ref, checkout.href jako checkout_url
 *   3. Redirect klienta na checkout.href
 *   4. Mollie pinguje webhookUrl na każdą zmianę statusu
 *   5. Pobieramy GET /v2/payments/{id} dla finalnego statusu
 */
class MolliePaymentProvider implements PaymentProviderInterface
{
    public const API_BASE = 'https://api.mollie.com/v2';

    /**
     * Methods Mollie obsługuje (PL/EU subset). Owner picks listę
     * w tenant settings; pusta lista = Mollie pokaże wszystkie aktywne
     * w jego koncie (Mollie defaultową logikę).
     *
     * @var array<int,string>
     */
    public const SUPPORTED_METHODS = [
        'creditcard', 'blik', 'p24', 'ideal', 'bancontact', 'eps',
        'giropay', 'sofort', 'banktransfer', 'paypal', 'applepay',
    ];

    /**
     * @return array<string,string>
     */
    public static function methodOptions(): array
    {
        return [
            'creditcard' => 'Karty kredytowe',
            'blik' => 'BLIK (PL)',
            'p24' => 'Przelewy24 (PL)',
            'ideal' => 'iDEAL (NL)',
            'bancontact' => 'Bancontact (BE)',
            'eps' => 'EPS (AT)',
            'giropay' => 'Giropay (DE)',
            'sofort' => 'Sofort',
            'banktransfer' => 'Przelew bankowy',
            'paypal' => 'PayPal',
            'applepay' => 'Apple Pay',
        ];
    }

    public function id(): string
    {
        return 'mollie';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $cfg = $this->resolveCredentials($tenant);

        $returnUrl = route('public.payments.return', [
            'slug' => $tenant->slug,
            'provider' => 'mollie',
            'payment' => $payment->id,
        ]);
        $webhookUrl = route('public.payments.webhook', [
            'slug' => $tenant->slug,
            'provider' => 'mollie',
        ]);

        $methods = $this->enabledMethods($tenant);

        $payload = [
            'amount' => [
                'currency' => $payment->currency,
                'value' => number_format($payment->amount_cents / 100, 2, '.', ''),
            ],
            'description' => $this->description($payment, $tenant),
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => [
                'hovera_payment_id' => $payment->id,
                'hovera_tenant_slug' => $tenant->slug,
            ],
        ];
        // Mollie: brak `method` → checkout pokazuje wszystko aktywne na koncie.
        // Single method → przekierowuje od razu do tej metody.
        // Multiple methods → checkout z listą tylko wybranych.
        if ($methods !== []) {
            $payload['method'] = count($methods) === 1 ? $methods[0] : $methods;
        }

        $response = Http::withToken($cfg['api_key'])
            ->acceptJson()
            ->asJson()
            ->post(self::API_BASE.'/payments', $payload);

        if (! $response->successful()) {
            $err = (string) data_get($response->json(), 'detail', 'unknown error');
            throw PaymentProviderException::apiError($this->id(), $err);
        }

        $body = $response->json();
        $url = (string) data_get($body, '_links.checkout.href', '');
        $molliePaymentId = (string) data_get($body, 'id', '');

        if ($url === '' || $molliePaymentId === '') {
            throw PaymentProviderException::apiError($this->id(), 'pusty payload odpowiedzi');
        }

        $payment->forceFill([
            'provider_ref' => $molliePaymentId,
            'checkout_url' => $url,
            'status' => PaymentStatus::Processing->value,
            'expires_at' => now()->addHours(24),
            'provider_data' => ['payment' => $body],
        ])->save();

        return $url;
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    /**
     * Mollie webhook: payload zawiera TYLKO `id` w postaci form-encoded.
     * Pobieramy GET /v2/payments/{id} dla rzeczywistego statusu.
     */
    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        $cfg = $this->resolveCredentials($tenant);

        $molliePaymentId = (string) $request->input('id', '');
        if ($molliePaymentId === '') {
            return response('missing id', 400);
        }

        $resp = Http::withToken($cfg['api_key'])
            ->acceptJson()
            ->get(self::API_BASE.'/payments/'.$molliePaymentId);

        if (! $resp->successful()) {
            Log::warning('Mollie webhook: fetch failed', [
                'tenant' => $tenant->slug,
                'payment' => $molliePaymentId,
                'status' => $resp->status(),
            ]);

            return response('fetch_failed', 502);
        }

        $payment = Payment::query()->where('provider_ref', $molliePaymentId)->first();
        if (! $payment) {
            Log::warning('Mollie webhook: unknown payment ref', ['ref' => $molliePaymentId]);

            return response('not_found', 404);
        }

        $newStatus = $this->mapStatus((string) data_get($resp->json(), 'status', ''));
        if ($newStatus === null) {
            // np. 'open' / 'pending' — payment wciąż w Processing, nic nie robimy
            return response('ok', 200);
        }

        // Idempotency
        if ($payment->status->isTerminal() && $payment->status !== $newStatus) {
            return response('ok', 200);
        }

        $payment->forceFill([
            'status' => $newStatus->value,
            'paid_at' => $newStatus === PaymentStatus::Succeeded ? now() : $payment->paid_at,
            'refunded_at' => $newStatus === PaymentStatus::Refunded ? now() : $payment->refunded_at,
        ])->save();

        return response('ok', 200);
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        $cfg = $this->resolveCredentials($tenant);

        $molliePaymentId = (string) $payment->provider_ref;
        if ($molliePaymentId === '') {
            throw PaymentProviderException::apiError($this->id(), 'brak provider_ref');
        }

        $resp = Http::withToken($cfg['api_key'])
            ->acceptJson()
            ->asJson()
            ->post(self::API_BASE.'/payments/'.$molliePaymentId.'/refunds', [
                'amount' => [
                    'currency' => $payment->currency,
                    'value' => number_format($payment->amount_cents / 100, 2, '.', ''),
                ],
            ]);

        if (! $resp->successful()) {
            $err = (string) data_get($resp->json(), 'detail', 'unknown');
            throw PaymentProviderException::apiError($this->id(), $err);
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded->value,
            'refunded_at' => now(),
        ])->save();
    }

    /**
     * Mapuje surowe statusy Mollie na nasz PaymentStatus enum. Zwraca null
     * dla statusów nie-terminalnych (open / pending / authorized) — wtedy
     * nie ruszamy lokalnego stanu.
     *
     * Reference: https://docs.mollie.com/payments/status-changes
     */
    private function mapStatus(string $mollieStatus): ?PaymentStatus
    {
        return match ($mollieStatus) {
            'paid' => PaymentStatus::Succeeded,
            'canceled', 'expired', 'failed' => PaymentStatus::Failed,
            // 'open', 'pending', 'authorized' → wciąż w toku
            default => null,
        };
    }

    /**
     * @return array{api_key:string}
     */
    private function resolveCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.mollie') ?? []);
        $key = (string) ($cfg['api_key'] ?? '');
        if ($key === '') {
            throw PaymentProviderException::notConfigured($this->id(), 'api_key');
        }
        try {
            $key = Crypt::decryptString($key);
        } catch (\Throwable) {
            // plain-text fallback dla testów
        }

        return ['api_key' => $key];
    }

    /**
     * @return array<int,string>
     */
    private function enabledMethods(Tenant $tenant): array
    {
        $methods = (array) (data_get($tenant->settings, 'payments.mollie.enabled_methods') ?? []);

        return array_values(array_intersect($methods, self::SUPPORTED_METHODS));
    }

    private function description(Payment $payment, Tenant $tenant): string
    {
        $kind = $payment->calendar_entry_id !== null
            ? 'Lekcja jeździecka'
            : ($payment->pass_id !== null ? 'Karnet' : 'Płatność');

        return $kind.' — '.$tenant->name;
    }
}
