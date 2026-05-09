<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\StripeWebhookEvent;
use App\Models\Central\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;

/**
 * Central billing service — drives hovera's own SaaS subscription
 * (each tenant pays for hovera itself). Per-tenant payment provider
 * config (P24/Mollie/PayU/Stripe) lives separately under
 * App\Services\Payments and is unrelated to this class.
 *
 * Public surface:
 *   - createCustomer(Tenant) → 'cus_*' (idempotent)
 *   - createCheckoutSession(Tenant, Plan, 'monthly'|'yearly') → URL
 *   - createPortalSession(Tenant) → URL
 *   - handleWebhook(payload, signature) → void (idempotent)
 */
class StripeBillingService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly string $secret,
        private readonly ?string $webhookSecret = null,
        private readonly int $webhookTolerance = 300,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('Stripe secret key is empty — set STRIPE_SECRET in .env.');
        }

        $this->stripe = new StripeClient($secret);
    }

    /**
     * Create or reuse a Stripe Customer for the tenant. Returns the
     * `cus_*` id and persists it on the tenant row so the next call is
     * a no-op even if the caller forgets to refresh.
     */
    public function createCustomer(Tenant $tenant): string
    {
        if ($tenant->stripe_customer_id !== null) {
            return $tenant->stripe_customer_id;
        }

        $ownerEmail = $this->ownerEmailFor($tenant);

        $customer = $this->stripe->customers->create([
            'name' => $tenant->legal_name ?: $tenant->name,
            'email' => $ownerEmail,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
            ],
        ]);

        $tenant->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Build a hosted Checkout Session and return the redirect URL.
     * `period` controls which Stripe Price id is used.
     *
     * @param  'monthly'|'yearly'  $period
     */
    public function createCheckoutSession(Tenant $tenant, Plan $plan, string $period): string
    {
        $priceId = $this->resolvePriceId($plan, $period);
        $customerId = $this->createCustomer($tenant);

        /** @var CheckoutSession $session */
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'allow_promotion_codes' => true,
            'success_url' => url('/app/billing/return').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('/app/billing'),
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'plan_code' => (string) $plan->code,
                    'period' => $period,
                ],
            ],
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_code' => (string) $plan->code,
                'period' => $period,
            ],
        ]);

        if ($session->url === null) {
            throw new RuntimeException('Stripe Checkout did not return a redirect URL.');
        }

        return (string) $session->url;
    }

    /**
     * Build a Customer Portal session — used for cancellations, card
     * updates, invoice history. We require an existing customer; without
     * one there's nothing to manage, so we throw.
     */
    public function createPortalSession(Tenant $tenant): string
    {
        if ($tenant->stripe_customer_id === null) {
            throw new RuntimeException('Tenant has no Stripe customer — cannot open portal.');
        }

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $tenant->stripe_customer_id,
            'return_url' => url('/app/billing'),
        ]);

        return (string) $session->url;
    }

    /**
     * Verify signature, dedupe by event id, route to handler. Caller
     * (StripeWebhookController) should always return 200 even on
     * dedupe — Stripe needs that to stop retrying.
     */
    public function handleWebhook(string $payload, string $signature): void
    {
        if ($this->webhookSecret === null || $this->webhookSecret === '') {
            throw new RuntimeException('STRIPE_WEBHOOK_SECRET is not configured.');
        }

        try {
            /** @var Event $event */
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret,
                $this->webhookTolerance,
            );
        } catch (SignatureVerificationException $e) {
            throw new RuntimeException('Stripe signature verification failed: '.$e->getMessage(), 0, $e);
        }

        // Idempotency — unique index on event_id makes the insert reject
        // duplicates atomically. Dupe = already processed = no-op.
        try {
            $row = StripeWebhookEvent::create([
                'event_id' => $event->id,
                'type' => $event->type,
                'payload' => json_decode(json_encode($event->toArray()), true) ?: [],
            ]);
        } catch (QueryException $e) {
            // Duplicate key on event_id — earlier delivery already
            // processed. Swallow and return 200 to Stripe.
            if ($this->isDuplicateKey($e)) {
                return;
            }
            throw $e;
        }

        try {
            $this->dispatch($event);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler failed', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            // Wipe the row so Stripe's retry can hit a clean slate.
            $row->delete();
            throw $e;
        }

        $row->forceFill(['processed_at' => now()])->save();
    }

    private function dispatch(Event $event): void
    {
        $object = $event->data->object ?? null;
        if ($object === null) {
            return;
        }

        $payload = $object instanceof StripeObject
            ? $object->toArray()
            : (array) $object;

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($payload),
            'customer.subscription.updated' => $this->onSubscriptionUpdated($payload),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($payload),
            'invoice.payment_failed' => $this->onInvoicePaymentFailed($payload),
            default => null,
        };
    }

    /**
     * @param  array<string,mixed>  $session
     */
    private function onCheckoutCompleted(array $session): void
    {
        $tenant = $this->tenantFromMetadata($session['metadata'] ?? null)
            ?? $this->tenantFromCustomer($session['customer'] ?? null);

        if ($tenant === null) {
            Log::warning('Stripe checkout.session.completed without resolvable tenant', $session);

            return;
        }

        $subscriptionId = is_string($session['subscription'] ?? null) ? $session['subscription'] : null;
        $update = ['status' => 'active'];

        if ($subscriptionId !== null) {
            $update['stripe_subscription_id'] = $subscriptionId;

            try {
                $sub = $this->stripe->subscriptions->retrieve($subscriptionId, []);
                $update['current_period_ends_at'] = isset($sub->current_period_end)
                    ? Carbon::createFromTimestamp((int) $sub->current_period_end)
                    : null;
            } catch (\Throwable $e) {
                Log::warning('Could not fetch Stripe subscription', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $tenant->forceFill($update)->save();
    }

    /**
     * @param  array<string,mixed>  $sub
     */
    private function onSubscriptionUpdated(array $sub): void
    {
        $tenant = $this->tenantFromMetadata($sub['metadata'] ?? null)
            ?? $this->tenantFromSubscriptionId($sub['id'] ?? null)
            ?? $this->tenantFromCustomer($sub['customer'] ?? null);

        if ($tenant === null) {
            return;
        }

        $update = [];

        if (isset($sub['current_period_end']) && is_int($sub['current_period_end'])) {
            $update['current_period_ends_at'] = Carbon::createFromTimestamp($sub['current_period_end']);
        }

        // Active again after past_due → restore status.
        $stripeStatus = (string) ($sub['status'] ?? '');
        if ($stripeStatus === 'active' && $tenant->status !== 'active') {
            $update['status'] = 'active';
        }
        if ($stripeStatus === 'past_due' && $tenant->status === 'active') {
            $update['status'] = 'past_due';
        }

        if ($update !== []) {
            $tenant->forceFill($update)->save();
        }
    }

    /**
     * @param  array<string,mixed>  $sub
     */
    private function onSubscriptionDeleted(array $sub): void
    {
        $tenant = $this->tenantFromMetadata($sub['metadata'] ?? null)
            ?? $this->tenantFromSubscriptionId($sub['id'] ?? null)
            ?? $this->tenantFromCustomer($sub['customer'] ?? null);

        if ($tenant === null) {
            return;
        }

        // Grace period until period end — flip to `trialing` so the
        // panel still works but the trial-expired middleware kicks in
        // once subscription_ends_at passes.
        $tenant->forceFill([
            'status' => 'trialing',
            'subscription_ends_at' => now(),
            'stripe_subscription_id' => null,
        ])->save();
    }

    /**
     * @param  array<string,mixed>  $invoice
     */
    private function onInvoicePaymentFailed(array $invoice): void
    {
        $tenant = $this->tenantFromCustomer($invoice['customer'] ?? null);
        if ($tenant === null) {
            return;
        }

        Log::warning('Stripe invoice payment failed', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice['id'] ?? null,
            'attempt_count' => $invoice['attempt_count'] ?? null,
        ]);

        // After 3 attempts (Stripe default smart retry), flip to suspended
        // so the trial-expired middleware blocks panel access.
        if ((int) ($invoice['attempt_count'] ?? 0) >= 3 && $tenant->status === 'active') {
            $tenant->forceFill(['status' => 'past_due'])->save();
        }
    }

    /**
     * @param  array<string,mixed>|null  $metadata
     */
    private function tenantFromMetadata(?array $metadata): ?Tenant
    {
        $id = is_array($metadata) ? ($metadata['tenant_id'] ?? null) : null;

        return is_string($id) && $id !== ''
            ? Tenant::find($id)
            : null;
    }

    private function tenantFromCustomer(mixed $customerId): ?Tenant
    {
        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        return Tenant::where('stripe_customer_id', $customerId)->first();
    }

    private function tenantFromSubscriptionId(mixed $subscriptionId): ?Tenant
    {
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return null;
        }

        return Tenant::where('stripe_subscription_id', $subscriptionId)->first();
    }

    /**
     * @param  'monthly'|'yearly'  $period
     */
    private function resolvePriceId(Plan $plan, string $period): string
    {
        $price = match ($period) {
            'monthly' => $plan->stripe_price_monthly_id,
            'yearly' => $plan->stripe_price_yearly_id,
            default => throw new InvalidArgumentException("Unknown billing period: $period"),
        };

        if (! is_string($price) || $price === '') {
            throw new RuntimeException(
                "Plan {$plan->code} has no stripe_price_{$period}_id configured."
            );
        }

        return $price;
    }

    private function ownerEmailFor(Tenant $tenant): ?string
    {
        $email = $tenant->memberships()
            ->where('role', 'owner')
            ->whereNull('revoked_at')
            ->orderBy('joined_at')
            ->limit(1)
            ->get()
            ->map(fn ($m) => $m->user?->email)
            ->filter()
            ->first();

        return is_string($email) ? $email : null;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        // 23000 = SQLSTATE integrity constraint violation (covers MySQL
        // 1062 duplicate entry + Postgres 23505 unique violation).
        return $code === '23000' || str_contains($e->getMessage(), 'Duplicate');
    }
}
