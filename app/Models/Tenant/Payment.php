<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends TenantModel
{
    protected $table = 'payments';

    protected $fillable = [
        'client_id',
        'calendar_entry_id',
        'pass_id',
        'invoice_id',
        'amount_cents',
        'currency',
        'provider',
        'provider_ref',
        'status',
        'provider_data',
        'checkout_url',
        'expires_at',
        'paid_at',
        'refunded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'provider_data' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function calendarEntry(): BelongsTo
    {
        return $this->belongsTo(CalendarEntry::class);
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PaymentStatus::Pending->value,
            PaymentStatus::Processing->value,
        ]);
    }

    public function scopeForClient(Builder $query, string $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function isOpen(): bool
    {
        return ! $this->status->isTerminal();
    }

    public function amountFormatted(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', ' ').' '.$this->currency;
    }
}
