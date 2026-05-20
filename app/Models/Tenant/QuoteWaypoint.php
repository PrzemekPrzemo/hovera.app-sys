<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Waypoint między pickup i dropoff w wycenie transportu. Patrz
 * docs/MARKETPLACE-ROADMAP.md "Waypoints + reorder + POI library".
 *
 * Wycena z waypoint'ami: distance liczy się jako suma segmentów
 * (pickup → wp1 → wp2 → ... → dropoff). CalculatorService dostaje
 * listę i przepuszcza przez RoutingService multi-leg.
 */
class QuoteWaypoint extends TenantModel
{
    public const KIND_STOP = 'stop';

    public const KIND_PICKUP = 'pickup';

    public const KIND_DROPOFF = 'dropoff';

    public const KIND_REST = 'rest';

    public const KIND_POI = 'poi';

    protected $table = 'quote_waypoints';

    protected $fillable = [
        'quote_id', 'sort_order', 'kind',
        'address', 'lat', 'lng',
        'poi_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function poi(): BelongsTo
    {
        return $this->belongsTo(Poi::class);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('created_at');
    }
}
