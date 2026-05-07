<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\PaymentProviderRegistry;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Str;

/**
 * Single entry-point for "let this client pay X". Picks the tenant's
 * default provider, creates a Payment row, hands off to the provider's
 * initiate(), returns the hosted-checkout URL.
 *
 * If the tenant has `payments.default_provider = none`, the action
 * throws — the calling code (UI / API) should hide the "Pay online"
 * button entirely in that case rather than letting users click into
 * an error.
 */
class InitiatePayment
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @param  array{calendar_entry_id?:?string, pass_id?:?string, metadata?:array<string,mixed>}  $context
     */
    public function execute(
        Tenant $tenant,
        Client $client,
        int $amountCents,
        string $currency = 'PLN',
        array $context = [],
    ): Payment {
        $provider = $this->registry->defaultFor($tenant);
        $providerEnum = PaymentProvider::from($provider->id());

        $payment = Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $client->id,
            'calendar_entry_id' => $context['calendar_entry_id'] ?? null,
            'pass_id' => $context['pass_id'] ?? null,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'provider' => $providerEnum->value,
            'status' => PaymentStatus::Pending->value,
            'metadata' => $context['metadata'] ?? null,
        ]);

        try {
            $url = $provider->initiate($tenant, $payment);
        } catch (PaymentProviderException $e) {
            $payment->forceFill([
                'status' => PaymentStatus::Failed->value,
                'metadata' => array_merge($payment->metadata ?? [], ['init_error' => $e->getMessage()]),
            ])->save();

            throw $e;
        }

        // Provider already wrote checkout_url + status=Processing on the row.
        // Re-fetch to reflect those changes for the caller.
        $payment->refresh();

        $this->audit->record('payment.initiated', 'Payment', (string) $payment->id, [
            'provider' => $providerEnum->value,
            'amount_cents' => $amountCents,
        ]);

        return $payment;
    }
}
