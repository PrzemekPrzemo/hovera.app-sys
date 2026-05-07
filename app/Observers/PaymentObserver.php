<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Tenant\Payment;

/**
 * Reaguje na zmianę statusu Payment. Kiedy płatność wskazuje na
 * konkretną fakturę (payments.invoice_id) i właśnie przeszła na
 * Succeeded, oznacz fakturę jako Paid (paid_at = now).
 *
 * Opieramy się na model `updated` event — ten sam mechanizm działa
 * niezależnie czy update przyszedł z webhooka providera (Stripe,
 * Mollie, P24, PayU), z rozliczenia ręcznego, czy z Stub-providera
 * w testach. Provider-specific kod (signature verify, status mapping)
 * został w providerach; my tu tylko propagujemy "succeeded" na
 * powiązaną fakturę.
 */
class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        $statusChanged = $payment->wasChanged('status');
        if (! $statusChanged) {
            return;
        }

        if ($payment->status !== PaymentStatus::Succeeded) {
            return;
        }

        if (! $payment->invoice_id) {
            return;
        }

        $invoice = $payment->invoice()->first();
        if (! $invoice) {
            return;
        }

        // Idempotency — jeśli już Paid (np. drugi webhook delivery)
        // nie nadpisuj paid_at.
        if ($invoice->status === InvoiceStatus::Paid) {
            return;
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => now(),
        ])->save();
    }
}
