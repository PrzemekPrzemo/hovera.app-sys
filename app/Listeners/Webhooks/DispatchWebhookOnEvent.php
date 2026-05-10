<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Services\Webhooks\WebhookDispatcher;

/**
 * Generic listener that bridges domain events to the webhook dispatcher.
 *
 * Convention: domain events that should fan out to tenant webhooks must
 * expose:
 *   - public string $tenantId
 *   - public string $webhookEvent  (e.g. "invoice.paid")
 *   - public function toWebhookPayload(): array
 *
 * Wiring is the future maintainer's job — this PR only ships the
 * infrastructure. Once we land actual events (InvoicePaid, BookingCreated),
 * register this listener for them in EventServiceProvider:
 *
 *     InvoicePaid::class => [DispatchWebhookOnEvent::class],
 *     BookingCreated::class => [DispatchWebhookOnEvent::class],
 */
class DispatchWebhookOnEvent
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(object $event): void
    {
        if (! property_exists($event, 'tenantId') || ! property_exists($event, 'webhookEvent')) {
            return;
        }

        if (! method_exists($event, 'toWebhookPayload')) {
            return;
        }

        $this->dispatcher->dispatch(
            tenantId: (string) $event->tenantId,
            event: (string) $event->webhookEvent,
            payload: (array) $event->toWebhookPayload(),
        );
    }
}
