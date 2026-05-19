<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Http\Controllers\Controller;
use App\Models\Central\StripeWebhookEvent;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;

/**
 * Public endpoint for Stripe Connect (per-transporter) webhook deliveries.
 * Patrz docs/TRANSPORT.md §15.6.
 *
 * ODDZIELNY endpoint od central /webhooks/stripe — w Stripe dashboardzie
 * konfigurujemy DWA webhook'i:
 *   - /webhooks/stripe          → platform events (subskrypcje hovery)
 *   - /webhooks/stripe-connect  → connect events (per-transporter charges)
 *
 * Każdy ma osobny signing secret (STRIPE_WEBHOOK_SECRET vs
 * STRIPE_CONNECT_WEBHOOK_SECRET) — żeby kompromis jednego nie obalał drugiego.
 *
 * Obsługiwane eventy:
 *   - account.updated                  → sync stripe_connect_status
 *   - checkout.session.completed       → mark quote as paid
 *   - payment_intent.succeeded         → mark quote as paid (fallback)
 *   - payment_intent.payment_failed    → log + optional notification
 *
 * Nieznane typy: 200 OK (ignored gracefully) — Stripe nie retry'uje.
 */
class StripeConnectWebhookController extends Controller
{
    public function __construct(
        private readonly TransporterStripeConnectService $connect,
        private readonly TenantManager $tenants,
        private readonly TenantAuditLogger $audit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) $request->header('Stripe-Signature', '');
        $payload = (string) $request->getContent();

        if ($signature === '' || $payload === '') {
            return response()->json(['error' => 'missing payload or signature'], 400);
        }

        $secret = (string) config('services.stripe.connect.webhook_secret', '');
        if ($secret === '') {
            Log::error('STRIPE_CONNECT_WEBHOOK_SECRET not configured.');

            return response()->json(['error' => 'webhook not configured'], 500);
        }

        try {
            /** @var Event $event */
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret,
                (int) config('services.stripe.webhook.tolerance', 300),
            );
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        // Idempotency — dedupe po event_id (unique index).
        try {
            $row = StripeWebhookEvent::create([
                'event_id' => $event->id,
                'type' => $event->type,
                'payload' => json_decode(json_encode($event->toArray()), true) ?: [],
            ]);
        } catch (QueryException $e) {
            // Duplicate: poprzednia delivery już obsłużona → 200, koniec.
            if ($this->isDuplicateKey($e)) {
                return response()->json(['received' => true, 'dedupe' => true]);
            }
            throw $e;
        }

        try {
            $this->dispatch($event);
        } catch (\Throwable $e) {
            Log::error('Stripe Connect webhook handler failed', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            // Wyczyść row żeby Stripe retry hit'nął clean slate.
            $row->delete();

            return response()->json(['error' => 'processing failed'], 500);
        }

        $row->forceFill(['processed_at' => now()])->save();

        return response()->json(['received' => true]);
    }

    private function dispatch(Event $event): void
    {
        $object = $event->data->object ?? null;
        if ($object === null) {
            return;
        }

        $payload = $object instanceof StripeObject ? $object->toArray() : (array) $object;

        match ($event->type) {
            'account.updated' => $this->onAccountUpdated($payload),
            'checkout.session.completed' => $this->onCheckoutCompleted($payload, $event),
            'payment_intent.succeeded' => $this->onPaymentIntentSucceeded($payload, $event),
            'payment_intent.payment_failed' => $this->onPaymentIntentFailed($payload, $event),
            default => null, // ignore
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function onAccountUpdated(array $payload): void
    {
        $accountId = (string) ($payload['id'] ?? '');
        if ($accountId === '') {
            return;
        }

        $tenant = Tenant::where('stripe_connect_account_id', $accountId)->first();
        if ($tenant === null) {
            Log::info('Stripe Connect account.updated for unknown account', ['account_id' => $accountId]);

            return;
        }

        $this->connect->syncAccountStatus($tenant);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function onCheckoutCompleted(array $payload, Event $event): void
    {
        // Connect events carry the account id at event level (event.account).
        // Fallback: payment_status check.
        $accountId = (string) ($event->account ?? '');
        $tenantId = (string) ($payload['metadata']['tenant_id'] ?? '');
        $quoteId = (string) ($payload['metadata']['quote_id'] ?? '');
        $paymentStatus = (string) ($payload['payment_status'] ?? '');

        if ($paymentStatus !== '' && $paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
            return;
        }

        $this->markQuotePaid($tenantId, $quoteId, $accountId, [
            'event' => 'checkout.session.completed',
            'session_id' => (string) ($payload['id'] ?? ''),
            'amount_total' => (int) ($payload['amount_total'] ?? 0),
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function onPaymentIntentSucceeded(array $payload, Event $event): void
    {
        $accountId = (string) ($event->account ?? '');
        $tenantId = (string) ($payload['metadata']['tenant_id'] ?? '');
        $quoteId = (string) ($payload['metadata']['quote_id'] ?? '');

        if ($tenantId === '' || $quoteId === '') {
            return;
        }

        $this->markQuotePaid($tenantId, $quoteId, $accountId, [
            'event' => 'payment_intent.succeeded',
            'payment_intent_id' => (string) ($payload['id'] ?? ''),
            'amount' => (int) ($payload['amount'] ?? 0),
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function onPaymentIntentFailed(array $payload, Event $event): void
    {
        $accountId = (string) ($event->account ?? '');
        $tenantId = (string) ($payload['metadata']['tenant_id'] ?? '');
        $quoteId = (string) ($payload['metadata']['quote_id'] ?? '');
        $errorMessage = (string) ($payload['last_payment_error']['message'] ?? 'unknown');

        Log::warning('Stripe Connect payment_intent.payment_failed', [
            'tenant_id' => $tenantId,
            'quote_id' => $quoteId,
            'stripe_account_id' => $accountId,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Wspólna ścieżka „znajdź tenant'a → przepnij connection → znajdź quote
     * → ustaw payment_completed_at". Idempotentne — drugi raz nie nadpisuje
     * istniejącego timestampu (klient nie powinien być billed dwa razy,
     * a my nie wysyłamy 2× notyfikacji).
     *
     * @param  array<string,mixed>  $auditPayload
     */
    private function markQuotePaid(string $tenantId, string $quoteId, string $stripeAccountId, array $auditPayload): void
    {
        if ($tenantId === '' || $quoteId === '') {
            return;
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            Log::warning('Stripe Connect webhook: tenant not found', ['tenant_id' => $tenantId]);

            return;
        }

        // Sanity: stripe_account_id na evencie MUSI matchować tenant'owi
        // — chroni przed cross-tenant smuggling'iem gdyby ktoś forge'ował
        // metadata.tenant_id przez własne konto Connect.
        if ($stripeAccountId !== '' && $stripeAccountId !== (string) $tenant->stripe_connect_account_id) {
            Log::warning('Stripe Connect webhook: account mismatch', [
                'event_account_id' => $stripeAccountId,
                'tenant_stripe_account_id' => $tenant->stripe_connect_account_id,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        // Przepnij na tenant DB żeby znaleźć quote'a + zapisać audit log.
        $previous = $this->tenants->current();
        try {
            $this->tenants->setCurrent($tenant);

            $quote = Quote::find($quoteId);
            if ($quote === null) {
                Log::warning('Stripe Connect webhook: quote not found', [
                    'tenant_id' => $tenantId,
                    'quote_id' => $quoteId,
                ]);

                return;
            }

            if ($quote->payment_completed_at !== null) {
                // Już oznaczone — idempotent no-op.
                return;
            }

            $quote->forceFill(['payment_completed_at' => now()])->save();

            $this->audit->record(
                'stripe.connect.payment_received',
                'Quote',
                (string) $quote->id,
                array_merge(['quote_number' => (string) $quote->number], $auditPayload),
            );
        } finally {
            // Restore previous context (or forget).
            if ($previous !== null) {
                $this->tenants->setCurrent($previous);
            } else {
                $this->tenants->forget();
            }
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        return $code === '23000' || str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE');
    }
}
