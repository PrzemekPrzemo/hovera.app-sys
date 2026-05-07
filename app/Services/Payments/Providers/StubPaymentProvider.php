<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-only provider. Returns a deterministic checkout URL (`/stub/...`)
 * so feature tests can exercise the whole flow without hitting an
 * external API. NEVER selectable from tenant settings UI.
 *
 * Webhook payload contract for tests:
 *   POST /payments/{provider}/webhook with body { "ref": "...", "status": "succeeded" }
 */
class StubPaymentProvider implements PaymentProviderInterface
{
    public function id(): string
    {
        return 'stub';
    }

    public function initiate(Tenant $tenant, Payment $payment): string
    {
        $url = "https://stub.hovera.test/checkout/{$payment->id}";

        $payment->forceFill([
            'provider_ref' => 'stub_'.$payment->id,
            'checkout_url' => $url,
            'status' => PaymentStatus::Processing->value,
            'provider_data' => ['note' => 'stub provider — no external call'],
        ])->save();

        return $url;
    }

    public function handleReturn(Request $request, Tenant $tenant, Payment $payment): Response
    {
        return response('OK', 200);
    }

    public function handleWebhook(Request $request, Tenant $tenant): Response
    {
        $ref = (string) $request->input('ref', '');
        $newStatus = (string) $request->input('status', '');

        $payment = Payment::query()->where('provider_ref', $ref)->first();
        if (! $payment) {
            return response('not_found', 404);
        }

        $status = PaymentStatus::tryFrom($newStatus);
        if (! $status || ! $status->isTerminal()) {
            return response('bad_status', 422);
        }

        $payment->forceFill([
            'status' => $status->value,
            'paid_at' => $status === PaymentStatus::Succeeded ? now() : null,
            'refunded_at' => $status === PaymentStatus::Refunded ? now() : null,
        ])->save();

        return response('ok', 200);
    }

    public function refund(Tenant $tenant, Payment $payment): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Refunded->value,
            'refunded_at' => now(),
        ])->save();
    }
}
