<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxAssignment extends TenantModel
{
    protected $table = 'box_assignments';

    protected $fillable = [
        'horse_id', 'box_id',
        'assigned_at', 'vacated_at',
        'reason', 'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'vacated_at' => 'datetime',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('vacated_at');
    }

    public function isActive(): bool
    {
        return $this->vacated_at === null;
    }

    public function durationDays(): ?int
    {
        if (! $this->assigned_at) {
            return null;
        }
        $end = $this->vacated_at ?? now();

        return (int) $this->assigned_at->diffInDays($end);
    }
}
