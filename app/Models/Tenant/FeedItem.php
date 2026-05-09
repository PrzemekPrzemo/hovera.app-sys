<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single feed type the stable stocks (e.g. owies, siano łąkowe, mash).
 * Current stock is computed on demand as SUM(stockMovements.delta).
 */
class FeedItem extends TenantModel
{
    protected $table = 'feed_items';

    protected $fillable = [
        'name', 'unit', 'low_stock_threshold',
        'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(FeedStockMovement::class)->orderByDesc('movement_date');
    }

    public function currentStock(): float
    {
        return (float) $this->stockMovements()->sum('delta');
    }

    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }

        return $this->currentStock() < (float) $this->low_stock_threshold;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
