<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only log of incoming Stripe webhook deliveries. The `event_id`
 * unique index makes idempotent inserts trivial — duplicate retries
 * collide on insert and we skip processing.
 */
class StripeWebhookEvent extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'stripe_webhook_events';

    protected $fillable = [
        'event_id', 'type', 'processed_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
