<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\HealthRecordType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthRecord extends TenantModel
{
    use SoftDeletes;

    protected $table = 'health_records';

    protected $fillable = [
        'horse_id', 'type',
        'performed_at', 'performed_by',
        'specialist_id',
        'summary', 'details',
        'next_due_at', 'cost_cents',
        'attachments', 'metadata',
        'created_by_central_user_id',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'next_due_at' => 'date',
            'attachments' => 'array',
            'metadata' => 'array',
            'cost_cents' => 'integer',
            'type' => HealthRecordType::class,
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    /**
     * Display name of who performed the procedure — prefer the linked
     * Specialist's name, fall back to free-text `performed_by` for
     * legacy entries or one-off contractors.
     */
    public function performedByLabel(): ?string
    {
        return $this->specialist?->name ?? ($this->performed_by ?: null);
    }

    /**
     * Records whose next_due_at lands in the [now, now + N days]
     * window. Used by the dashboard alerts widget.
     */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('next_due_at')
            ->whereBetween('next_due_at', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    /**
     * Records whose next_due_at is already in the past — overdue.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->next_due_at !== null && $this->next_due_at->isPast();
    }

    public function daysUntilDue(): ?int
    {
        if (! $this->next_due_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->next_due_at, false);
    }
}
