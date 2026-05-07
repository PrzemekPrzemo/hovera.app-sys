<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\BoardingFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardingService extends TenantModel
{
    use SoftDeletes;

    protected $table = 'boarding_services';

    protected $fillable = [
        'name', 'description', 'unit', 'frequency',
        'price_cents', 'vat_rate', 'is_active', 'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => BoardingFrequency::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'price_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function horses(): BelongsToMany
    {
        return $this->belongsToMany(Horse::class, 'horse_boarding_services')
            ->withPivot(['price_override_cents', 'quantity', 'starts_at', 'ends_at', 'notes'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function priceFormatted(): string
    {
        return number_format($this->price_cents / 100, 2, ',', ' ').' zł';
    }

    /**
     * Heurystyka: jak naliczy się w skali miesiąca dla domyślnej ilości
     * (quantity=1). Klient widzi to jako "indykator" kosztu.
     */
    public function monthlyEquivalentCents(float $quantity = 1.0): int
    {
        return (int) round($this->price_cents * $quantity * $this->frequency->monthlyMultiplier());
    }
}
