<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Models\Central\WebhookDelivery;
use App\Models\Central\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POSTs a signed webhook to one subscription. Retries up to 3x with
 * exponential backoff on 5xx / network errors. 4xx responses are
 * terminal — caller has to fix their handler.
 *
 * Signature header is `X-Hovera-Signature: sha256=<hmac-hex>` computed
 * over the raw JSON body using the subscription's `secret`. Compatible
 * with Stripe-style verification on the receiver side.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Backoff in seconds for retries: 10s → 60s → 5min.
     *
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * @param  array<string,mixed>  $body
     */
    public function __construct(
        public string $subscriptionId,
        public string $event,
        public array $body,
        public int $attemptNumber = 1,
    ) {}

    public function handle(): void
    {
        $subscription = WebhookSubscription::find($this->subscriptionId);
        if (! $subscription || ! $subscription->is_active) {
            return;
        }

        $payloadJson = json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        $signature = 'sha256='.hash_hmac('sha256', $payloadJson, $subscription->secret);

        $startedAt = microtime(true);
        $statusCode = null;
        $responseBody = null;
        $errorMessage = null;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Hovera-Signature' => $signature,
                    'X-Hovera-Event' => $this->event,
                    'X-Hovera-Delivery-Attempt' => (string) $this->attemptNumber,
                    'User-Agent' => 'Hovera-Webhooks/1.0',
                ])
                ->withBody($payloadJson, 'application/json')
                ->post($subscription->url);

            $statusCode = $response->status();
            $responseBody = mb_substr((string) $response->body(), 0, 4000);
        } catch (Throwable $e) {
            $errorMessage = mb_substr($e->getMessage(), 0, 2000);
            Log::warning('webhook.delivery_failed', [
                'subscription_id' => $subscription->id,
                'event' => $this->event,
                'attempt' => $this->attemptNumber,
                'error' => $errorMessage,
            ]);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        WebhookDelivery::create([
            'subscription_id' => $subscription->id,
            'event' => $this->event,
            'payload' => $this->body,
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'attempt_number' => $this->attemptNumber,
            'delivered_at' => now(),
            'error_message' => $errorMessage,
        ]);

        $isSuccess = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
        $isClientError = $statusCode !== null && $statusCode >= 400 && $statusCode < 500;

        $subscription->forceFill([
            'last_delivery_at' => now(),
            'last_delivery_status' => match (true) {
                $isSuccess => 'success',
                $isClientError => 'client_error',
                default => 'failed',
            },
        ])->save();

        if ($isSuccess || $isClientError) {
            return;
        }

        // Network error or 5xx — bump attempt + release for retry until
        // tries exhausted; framework will then call $this->failed().
        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 60);
        }
    }
}
