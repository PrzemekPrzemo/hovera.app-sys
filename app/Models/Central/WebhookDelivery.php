<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per delivery attempt. Multiple rows per (subscription, event)
 * are normal because retries on 5xx bump attempt_number and create new
 * records — keeps a full audit trail for debugging delivery failures.
 */
class WebhookDelivery extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'subscription_id', 'event', 'payload',
        'status_code', 'response_body', 'duration_ms',
        'attempt_number', 'delivered_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'attempt_number' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }

    public function isSuccess(): bool
    {
        return $this->status_code !== null && $this->status_code >= 200 && $this->status_code < 300;
    }
}
