<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Budynek w stajni — opcjonalna grupa boksów. "Stajnia czerwona",
 * "Stajnia nowa", "Pawilon padokowy" itp. Stable owner decyduje
 * ile budynków ma stajnia i przypisuje boksy do nich.
 */
class Building extends TenantModel
{
    use SoftDeletes;

    protected $table = 'buildings';

    protected $fillable = [
        'name', 'notes', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }
}
