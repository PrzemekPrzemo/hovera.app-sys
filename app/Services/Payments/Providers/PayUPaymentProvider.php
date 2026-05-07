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
 * PayU REST API integration — DOCS:
 *   https://developers.payu.com/europe/docs/getting-started/
 *
 * Required tenant credentials (settings.payments.payu):
 *   pos_id           (numeric)
 *   client_id        (oauth2)
 *   client_secret    (oauth2)
 *   md5_key          (signature)
 *   sandbox          (bool)
 *
 * Flow:
 *   1. OAuth2 token from /pl/standard/user/oauth/authorize
 *   2. POST /api/v2_1/orders → returns {redirectUri, orderId}
 *   3. Redirect user to redirectUri
 *   4. Webhook to /payments/payu/webhook with OpenPayU notification
 *
 * REAL API CALLS NOT YET IMPLEMENTED — see follow-up PR.
 */
class PayUPaymentProvider implements PaymentProviderInterface
{
    public function id(): string
    {
        return 'payu';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $this->requireCredentials($tenant);

        // TODO: OAuth2 token + POST /api/v2_1/orders.
        throw PaymentProviderException::notImplemented($this->id(), 'initiate');
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        // TODO: verify OpenPayU-Signature header, parse notification, update status.
        throw PaymentProviderException::notImplemented($this->id(), 'handleWebhook');
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        // TODO: POST /api/v2_1/orders/{orderId}/refunds.
        throw PaymentProviderException::notImplemented($this->id(), 'refund');
    }

    private function requireCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.payu') ?? []);
        foreach (['pos_id', 'client_id', 'client_secret', 'md5_key'] as $k) {
            if (empty($cfg[$k])) {
                throw PaymentProviderException::notConfigured($this->id(), $k);
            }
        }

        return $cfg;
    }
}
