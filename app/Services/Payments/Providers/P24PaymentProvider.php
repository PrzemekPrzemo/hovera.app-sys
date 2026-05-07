<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentProviderException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Przelewy24 (P24) integration — DOCS:
 *   https://docs.przelewy24.pl/
 *
 * Required tenant credentials (settings.payments.p24):
 *   merchant_id   (numeric, e.g. 12345)
 *   pos_id        (often same as merchant_id for single-POS accounts)
 *   crc_key       (CRC for signing)
 *   api_key       (for REST API authentication)
 *   sandbox       (bool, true for sandbox.przelewy24.pl)
 *
 * Flow:
 *   1. POST /api/v1/transaction/register   → returns {token}
 *   2. Redirect user to {host}/trnRequest/{token}
 *   3. P24 webhooks back to /payments/p24/webhook with TRNStatus
 *   4. POST /api/v1/transaction/verify     → final confirmation
 *
 * REAL API CALLS NOT YET IMPLEMENTED — landing in a follow-up PR
 * (see GitHub issue / next iteration). All methods throw a clear
 * "not configured" until then.
 */
class P24PaymentProvider implements PaymentProviderInterface
{
    public function id(): string
    {
        return 'p24';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $this->requireCredentials($tenant);

        // TODO: real P24 transaction/register call.
        throw PaymentProviderException::notImplemented($this->id(), 'initiate');
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        // TODO: verify CRC signature, lookup payment by sessionId,
        // call /api/v1/transaction/verify, update status.
        throw PaymentProviderException::notImplemented($this->id(), 'handleWebhook');
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        // TODO: P24 supports refunds via /api/v1/transaction/refund.
        throw PaymentProviderException::notImplemented($this->id(), 'refund');
    }

    private function requireCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.p24') ?? []);
        foreach (['merchant_id', 'pos_id', 'crc_key', 'api_key'] as $k) {
            if (empty($cfg[$k])) {
                throw PaymentProviderException::notConfigured($this->id(), $k);
            }
        }

        return $cfg;
    }
}
