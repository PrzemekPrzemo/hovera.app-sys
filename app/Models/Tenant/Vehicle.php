<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends TenantModel
{
    use SoftDeletes;

    protected $table = 'vehicles';

    protected $fillable = [
        'name', 'registration_plate', 'capacity_horses',
        'gross_weight_kg', 'payload_kg', 'year_of_manufacture',
        'photos',
        'has_air_suspension', 'has_camera', 'has_climate_control',
        'notes',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'capacity_horses' => 'integer',
            'gross_weight_kg' => 'integer',
            'payload_kg' => 'integer',
            'year_of_manufacture' => 'integer',
            'has_air_suspension' => 'boolean',
            'has_camera' => 'boolean',
            'has_climate_control' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
