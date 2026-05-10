<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Models\Central\WebhookSubscription;

/**
 * Resolves active subscriptions for a (tenant, event) pair and queues
 * a DeliverWebhookJob per matching subscription. Domain code calls this
 * from event listeners — never POSTs synchronously inside a request,
 * because slow receiver endpoints would block the whole transaction.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string,mixed>  $payload
     * @return int  Number of subscriptions enqueued.
     */
    public function dispatch(string $tenantId, string $event, array $payload): int
    {
        $subscriptions = WebhookSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookSubscription $s) => $s->listensTo($event));

        $body = [
            'event' => $event,
            'tenant_id' => $tenantId,
            'occurred_at' => now()->toIso8601String(),
            'data' => $payload,
        ];

        foreach ($subscriptions as $subscription) {
            DeliverWebhookJob::dispatch($subscription->id, $event, $body);
        }

        return $subscriptions->count();
    }
}
