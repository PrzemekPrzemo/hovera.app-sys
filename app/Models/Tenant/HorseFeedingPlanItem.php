<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\FeedingMeal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per "what to feed at which meal" for a single horse.
 * Visible to the boarder in the client portal — transparency over
 * what their horse actually eats day to day.
 */
class HorseFeedingPlanItem extends TenantModel
{
    protected $table = 'horse_feeding_plan_items';

    protected $fillable = [
        'horse_id',
        'meal', 'feed_type', 'amount_kg', 'unit',
        'notes',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'meal' => FeedingMeal::class,
            'amount_kg' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function amountFormatted(): string
    {
        // Strip trailing .00 — show "2,5" not "2.50"
        $value = rtrim(rtrim(number_format((float) $this->amount_kg, 2, ',', ''), '0'), ',');

        return $value.' '.$this->unit;
    }
}
