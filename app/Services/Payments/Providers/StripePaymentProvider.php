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
 * Stripe Checkout integration — DOCS:
 *   https://stripe.com/docs/api/checkout/sessions
 *
 * Required tenant credentials (settings.payments.stripe):
 *   secret_key        (sk_live_... or sk_test_...)
 *   webhook_secret    (whsec_..., for signature verification)
 *   publishable_key   (pk_live_..., shown in client widget if used)
 *
 * Flow:
 *   1. POST /v1/checkout/sessions → returns {id, url}
 *   2. Redirect user to session.url
 *   3. Webhook to /payments/stripe/webhook with checkout.session.completed
 *
 * REAL API CALLS NOT YET IMPLEMENTED — see follow-up PR.
 * Recommended package: stripe/stripe-php (composer require stripe/stripe-php).
 */
class StripePaymentProvider implements PaymentProviderInterface
{
    public function id(): string
    {
        return 'stripe';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $this->requireCredentials($tenant);

        // TODO: \Stripe\Checkout\Session::create([...]) and persist session id.
        throw PaymentProviderException::notImplemented($this->id(), 'initiate');
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        // TODO: verify Stripe-Signature header via \Stripe\Webhook::constructEvent.
        throw PaymentProviderException::notImplemented($this->id(), 'handleWebhook');
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        // TODO: \Stripe\Refund::create(['payment_intent' => ...]).
        throw PaymentProviderException::notImplemented($this->id(), 'refund');
    }

    private function requireCredentials(Tenant $tenant): array
    {
        $cfg = (array) (data_get($tenant->settings, 'payments.stripe') ?? []);
        foreach (['secret_key', 'webhook_secret'] as $k) {
            if (empty($cfg[$k])) {
                throw PaymentProviderException::notConfigured($this->id(), $k);
            }
        }

        return $cfg;
    }
}
