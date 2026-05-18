<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache obliczonych tras — patrz docs/TRANSPORT.md §7.4. Trasa Warszawa→Poznań
 * nie zmienia się z dnia na dzień, a Google billing tym sposobem spada
 * gwałtownie dla popularnych par. Klucz: hash(from + to + profile + provider).
 */
class RouteCache extends Model
{
    protected $connection = 'central';

    protected $table = 'route_cache';

    protected $fillable = [
        'cache_key', 'provider_id', 'profile',
        'from_lat', 'from_lng', 'to_lat', 'to_lng',
        'distance_km', 'duration_seconds', 'polyline',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'from_lat' => 'float',
            'from_lng' => 'float',
            'to_lat' => 'float',
            'to_lng' => 'float',
            'distance_km' => 'decimal:2',
            'duration_seconds' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
