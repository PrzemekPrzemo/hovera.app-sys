<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridge between Hovera and a hosted-checkout provider (Przelewy24, PayU,
 * Stripe, Mollie, ...).
 *
 *  initiate()    create a session with the provider, return a checkout URL
 *  handleReturn() process the synchronous redirect back from the provider
 *                — typically just shows a "thanks" page, the truth comes
 *                via webhook
 *  handleWebhook() async callback from the provider; verifies signature,
 *                  updates payment status, returns the response that
 *                  satisfies the provider
 *
 * Implementations are stateless services — keep secrets in tenant
 * settings, not in instance state.
 */
interface PaymentProviderInterface
{
    /**
     * Provider identifier ("p24", "stripe", ...) — must match the value
     * persisted on payments.provider.
     */
    public function id(): string;

    /**
     * Hand the user off to the provider's hosted checkout. Returns the
     * URL the client should be redirected to. The provider may also
     * mutate the Payment row (provider_ref, checkout_url, expires_at,
     * provider_data). Status moves pending → processing.
     *
     * Throws PaymentInitiationException on configuration / API errors.
     */
    public function initiate(Tenant $tenant, Payment $payment): string;

    /**
     * The synchronous return URL the provider redirects the user to.
     * Often we just trust the webhook for status — this method is for
     * UX (show "thanks" / "still processing"). It MUST NOT mark a
     * payment as succeeded on its own.
     */
    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response;

    /**
     * Async webhook from the provider. MUST verify signature/auth.
     * Idempotent — webhook can be retried.
     *
     * Returns the HTTP response the provider expects (typically 200 OK
     * with a tiny body or no body). Updates Payment status as warranted.
     */
    public function handleWebhook(Request $request, Tenant $tenant): Response;

    /**
     * Best-effort refund. Many providers require special permissions or
     * a manual approval — implementations may throw NotImplementedException
     * if they can't refund automatically.
     */
    public function refund(Tenant $tenant, Payment $payment): void;
}
