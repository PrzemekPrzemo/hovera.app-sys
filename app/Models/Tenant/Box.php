<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends TenantModel
{
    use SoftDeletes;

    protected $table = 'boxes';

    protected $fillable = [
        'name', 'label', 'type', 'size_m2', 'capacity',
        'monthly_rate_cents', 'is_active', 'sort_order',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'size_m2' => 'integer',
            'capacity' => 'integer',
            'monthly_rate_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function horses(): HasMany
    {
        return $this->hasMany(Horse::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BoxAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('vacated_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Box dostępny do nowego pensjonariusza — aktywny + pojemność
     * niewykorzystana w pełni.
     */
    public function isVacant(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $occupied = $this->horses()->count();

        return $occupied < (int) $this->capacity;
    }

    /**
     * Liczba miejsc wolnych w boxie (zwykle 0 albo 1, ale można ustawić
     * capacity=2 dla większych boksów grupowych).
     */
    public function freeSpots(): int
    {
        if (! $this->is_active) {
            return 0;
        }

        return max(0, (int) $this->capacity - $this->horses()->count());
    }

    public function monthlyRateFormatted(): ?string
    {
        if (! $this->monthly_rate_cents) {
            return null;
        }

        return number_format($this->monthly_rate_cents / 100, 2, ',', ' ').' zł';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'indoor' => 'Box wewnętrzny',
            'paddock' => 'Padok',
            'outdoor' => 'Box zewnętrzny',
            'quarantine' => 'Kwarantanna',
            default => $this->type,
        };
    }
}
