<?php

declare(strict_types=1);

namespace App\Domain\Invoicing\Owner;

use App\Actions\Payments\InitiatePayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

/**
 * Cross-tenant payment initiation dla owner panel'u (C.6 z OWNER-STABLE-
 * ROADMAP). Owner klika "Zapłać" na fakturze (w swoim panel'u) → ten
 * service:
 *   1. Walidacja: owner JEST klientem na tej fakturze (Client.central_user_id
 *      = Auth::id()). Invoice istnieje i nie jest paid/void/draft.
 *   2. TenantManager::execute do stable tenant'a żeby `InitiatePayment`
 *      mógł użyć stable creds (P24/PayU/Stripe — `payments.default_provider`
 *      ze stable.settings).
 *   3. Zwraca checkout URL.
 *
 * Provider creds (P24 merchant_id/crc/api_key, PayU pos_id, Stripe key)
 * trzymane są na `Tenant.settings.payments.{provider}.*` per stajnia —
 * każda stajnia ma swój ID merchant'a, hovera tylko mediuje routing.
 *
 * Webhook callback od provider'a wraca do istniejących endpointów
 * (`/payments/p24/webhook`, `/payments/payu/webhook` itd.), które
 * znajdują payment row po `provider_ref` + verify sign + mark
 * status=succeeded → PaymentObserver oznacza linked Invoice jako paid.
 */
class OwnerInvoicePaymentService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Inicjuje payment dla faktury i zwraca redirect URL na hosted
     * checkout providera. Throws gdy provider nie skonfigurowany,
     * owner nie ma access, faktura już opłacona itp.
     */
    public function initiate(User $owner, string $stableTenantId, string $invoiceId): string
    {
        $stable = Tenant::query()->find($stableTenantId);
        if ($stable === null) {
            throw new RuntimeException(__('owner/invoices.pay.stable_missing'));
        }

        if (! $this->stableSupportsPayments($stable)) {
            throw new RuntimeException(__('owner/invoices.pay.provider_not_configured'));
        }

        return $this->tenants->execute($stable, function () use ($owner, $stable, $invoiceId): string {
            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null) {
                throw new AuthorizationException(__('owner/invoices.pay.not_your_invoice'));
            }

            $invoice = Invoice::query()
                ->where('id', $invoiceId)
                ->where('client_id', $client->id)
                ->first();

            if ($invoice === null) {
                throw new AuthorizationException(__('owner/invoices.pay.not_your_invoice'));
            }

            if ($invoice->status === InvoiceStatus::Paid) {
                throw new RuntimeException(__('owner/invoices.pay.already_paid'));
            }

            if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Cancelled], true)) {
                throw new RuntimeException(__('owner/invoices.pay.not_payable', ['status' => $invoice->status->value]));
            }

            $payment = app(InitiatePayment::class)->execute(
                tenant: $stable,
                client: $client,
                amountCents: (int) $invoice->total_cents,
                currency: (string) $invoice->currency,
                context: [
                    'invoice_id' => $invoice->id,
                    'metadata' => [
                        'source' => 'owner_panel_pay_button',
                        'invoice_number' => $invoice->number,
                    ],
                ],
            );

            $checkoutUrl = (string) ($payment->checkout_url ?? '');
            if ($checkoutUrl === '') {
                throw new RuntimeException(__('owner/invoices.pay.no_checkout_url'));
            }

            // Link Payment → Invoice (InitiatePayment context['invoice_id']
            // nie jest w fillable bo `invoice_id` to standard relation;
            // doczepiamy ręcznie).
            $payment->forceFill(['invoice_id' => $invoice->id])->save();

            return $checkoutUrl;
        });
    }

    /**
     * Czy stajnia ma skonfigurowany payment provider (= jest co
     * inicjować). Owner UI powinien ukryć button gdy false.
     */
    public function stableSupportsPayments(Tenant $stable): bool
    {
        $providerCode = (string) (data_get($stable->settings, 'payments.default_provider') ?? 'none');
        $provider = PaymentProvider::tryFrom($providerCode) ?? PaymentProvider::None;

        return $provider !== PaymentProvider::None;
    }
}
