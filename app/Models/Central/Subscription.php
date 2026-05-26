<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'subscriptions';

    protected $fillable = [
        'tenant_id', 'plan_id',
        'status', 'billing_cycle',
        'current_period_start', 'current_period_end', 'cancelled_at',
        'stripe_subscription_id', 'p24_subscription_ref',
        'payu_recurring_token', 'payu_card_mask', 'payu_card_brand',
        'payu_card_expires_at',
        'payu_last_charge_status', 'payu_last_failed_at', 'payu_failed_attempts',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'payu_card_expires_at' => 'date',
            'payu_last_failed_at' => 'datetime',
            // Token jest płatniczy → trzymamy zaszyfrowane at-rest (Laravel
            // Crypt, klucz z APP_KEY). Decrypt nastąpi tylko w PayUService
            // podczas wywołania chargeRecurring().
            'payu_recurring_token' => 'encrypted',
            'payu_failed_attempts' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function hasPayuRecurring(): bool
    {
        return $this->payu_recurring_token !== null && $this->payu_recurring_token !== '';
    }
}
