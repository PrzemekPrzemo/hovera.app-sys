<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ulubione trasy ownera — saved templates (label + pickup/dropoff +
 * notes + optional default horse). Wybierane z dropdownu w
 * `/owner/order-transport` żeby przyspieszyć powtarzalne zlecenia
 * (klinika weterynaryjna, zawody, znana stajnia).
 */
class OwnerFavoriteRoute extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'owner_favorite_routes';

    protected $fillable = [
        'owner_user_id',
        'label',
        'pickup_address',
        'dropoff_address',
        'notes',
        'default_horse_central_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
