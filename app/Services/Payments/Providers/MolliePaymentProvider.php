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
 * Mollie integration — DOCS:
 *   https://docs.mollie.com/reference/v2/payments-api/create-payment
 *
 * Strong choice for EU stables — Mollie supports BLIK (PL), iDEAL (NL),
 * Bancontact (BE), Giropay (DE), SEPA + cards as a single integration.
 *
 * Required tenant credentials (settings.payments.mollie):
 *   api_key   (live_xxx... or test_xxx...)
 *
 * Flow:
 *   1. POST /v2/payments → returns {id, _links.checkout.href}
 *   2. Redirect user to checkout url
 *   3. Mollie pings webhookUrl on every status change; we GET the
 *      payment back to verify (Mollie webhooks don't carry status,
 *      forcing the verify-by-fetch pattern → very hard to fake)
 *
 * REAL API CALLS NOT YET IMPLEMENTED — see follow-up PR.
 * Recommended package: mollie/mollie-api-php.
 */
class MolliePaymentProvider implements PaymentProviderInterface
{
    public function id(): string
    {
        return 'mollie';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $this->requireCredentials($tenant);

        // TODO: $mollie->payments->create([...]) and persist payment id.
        throw PaymentProviderException::notImplemented($this->id(), 'initiate');
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        // TODO: GET $mollie->payments->get($id), update status from response.
        // (Mollie's pattern: webhook only delivers id, you must verify by fetch.)
        throw PaymentProviderException::notImplemented($this->id(), 'handleWebhook');
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        // TODO: $mollie->payments->refund($payment, ['amount' => ...]).
        throw PaymentProviderException::notImplemented($this->id(), 'refund');
    }

    private function requireCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.mollie') ?? []);
        if (empty($cfg['api_key'])) {
            throw PaymentProviderException::notConfigured($this->id(), 'api_key');
        }

        return $cfg;
    }
}
