<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class TransportServiceArea extends Model
{
    protected $connection = 'central';

    protected $table = 'transport_service_areas';

    protected $fillable = [
        'transporter_tenant_id', 'voivodeship',
    ];
}
