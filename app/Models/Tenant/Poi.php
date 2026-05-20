<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * POI library — punkt interesujący w bibliotece transportera.
 * Reuse'owalne lokalizacje (baza, stajnie, parkingi, paliwo).
 * Patrz docs/MARKETPLACE-ROADMAP.md "Waypoints + reorder + POI library".
 */
class Poi extends TenantModel
{
    use SoftDeletes;

    public const KIND_BASE = 'base';

    public const KIND_STABLE = 'stable';

    public const KIND_PARKING = 'parking';

    public const KIND_FUEL = 'fuel';

    public const KIND_OTHER = 'other';

    protected $table = 'pois';

    protected $fillable = [
        'name', 'kind',
        'address', 'lat', 'lng',
        'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }
}
