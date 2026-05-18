<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class TransportFavorite extends Model
{
    protected $connection = 'central';

    protected $table = 'transport_favorites';

    protected $fillable = [
        'stable_tenant_id', 'user_id', 'transporter_tenant_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
