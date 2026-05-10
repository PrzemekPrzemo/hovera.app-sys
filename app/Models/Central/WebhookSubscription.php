<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Per-tenant outbound webhook subscription. Tenants attach a URL to a set
 * of events; WebhookDispatcher fans out and DeliverWebhookJob signs &
 * POSTs the body. Master admin sees all subscriptions across tenants.
 */
class WebhookSubscription extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'webhook_subscriptions';

    protected $fillable = [
        'tenant_id', 'url', 'events', 'secret',
        'is_active', 'last_delivery_at', 'last_delivery_status',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }

    /**
     * Generate a fresh signing secret. Returned in plaintext so the
     * caller can persist it; rotate by calling and saving again.
     */
    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    public function listensTo(string $event): bool
    {
        return $this->is_active && in_array($event, (array) ($this->events ?? []), true);
    }
}
