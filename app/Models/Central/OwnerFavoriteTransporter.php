<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ulubieni przewoźnicy ownera — jeden wpis per (owner, transporter).
 * Używany do "Wyślij tylko do moich ulubionych" w `/owner/order-transport`.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md (future enhancements).
 */
class OwnerFavoriteTransporter extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'owner_favorite_transporters';

    protected $fillable = [
        'owner_user_id',
        'transporter_tenant_id',
        'notes',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'transporter_tenant_id');
    }
}
