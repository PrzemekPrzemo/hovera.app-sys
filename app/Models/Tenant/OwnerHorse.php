<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lightweight wrapper na `horses` table — dedykowany dla owner panel'u.
 *
 * Owner reuse'uje schema stable'a (patrz OwnerPanelProvider docblock §3),
 * ale jego UI eksponuje tylko ~8 pól (vs 30+ w stable HorseResource).
 * Trzymanie OSOBNEGO modelu ułatwia:
 *   - clear separation w resource (każdy Resource ma swój model)
 *   - inny `$fillable` (owner nie powinien pisać do `owner_client_id`,
 *     `box_id`, `cover_image_path` itp.)
 *   - przyszły migration path do PR 4/5 (cross-tenant horse registry —
 *     wtedy OwnerHorse zyska `central_horse_id` + relację do
 *     central_horse_registry).
 *
 * Table = `horses` (sharing z TenantModel/Horse). Tenant connection
 * setowany przez TenantManager — owner tenant DB ma własną kopię.
 */
class OwnerHorse extends TenantModel
{
    use SoftDeletes;

    protected $table = 'horses';

    protected $fillable = [
        'central_horse_id',
        'name',
        'breed',
        'birth_date',
        'sex',
        'color',
        'passport_number',
        'microchip',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }
}
