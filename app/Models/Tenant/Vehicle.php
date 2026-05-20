<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends TenantModel
{
    use SoftDeletes;

    protected $table = 'vehicles';

    protected $fillable = [
        'name', 'vehicle_type', 'registration_plate', 'capacity_horses',
        'gross_weight_kg', 'height_cm', 'payload_kg', 'year_of_manufacture',
        'photos',
        'has_air_suspension', 'has_camera', 'has_climate_control',
        'notes',
        'is_active', 'sort_order',
    ];

    protected $attributes = [
        'vehicle_type' => 'truck',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'photos' => 'array',
            'capacity_horses' => 'integer',
            'gross_weight_kg' => 'integer',
            'height_cm' => 'integer',
            'payload_kg' => 'integer',
            'year_of_manufacture' => 'integer',
            'has_air_suspension' => 'boolean',
            'has_camera' => 'boolean',
            'has_climate_control' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isTrailer(): bool
    {
        return $this->vehicle_type === VehicleType::Trailer;
    }

    /** @param Builder<Vehicle> $query */
    public function scopeTrucks(Builder $query): Builder
    {
        return $query->where('vehicle_type', VehicleType::Truck->value);
    }

    /** @param Builder<Vehicle> $query */
    public function scopeTrailers(Builder $query): Builder
    {
        return $query->where('vehicle_type', VehicleType::Trailer->value);
    }
}
