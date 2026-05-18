<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends TenantModel
{
    use SoftDeletes;

    protected $table = 'quotes';

    protected $fillable = [
        'number', 'status',
        'customer_name', 'customer_email', 'customer_phone',
        'customer_company', 'customer_tax_id', 'customer_address',
        'pickup_address', 'pickup_lat', 'pickup_lng',
        'dropoff_address', 'dropoff_lat', 'dropoff_lng',
        'preferred_date', 'preferred_time', 'round_trip', 'loaded',
        'vehicle_id', 'driver_id',
        'distance_km', 'duration_seconds', 'routing_provider', 'polyline',
        'rate_per_km', 'base_cost', 'fuel_surcharge', 'minimum_adjustment',
        'net_total', 'vat_rate', 'vat_amount', 'gross_total', 'currency',
        'terms', 'notes', 'valid_until',
        'accept_token',
        'sent_at', 'accepted_at', 'rejected_at', 'expired_at', 'withdrawn_at',
        'lead_id', 'response_id',
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'preferred_date' => 'date',
            'valid_until' => 'date',
            'round_trip' => 'boolean',
            'loaded' => 'boolean',
            'distance_km' => 'decimal:2',
            'duration_seconds' => 'integer',
            'rate_per_km' => 'decimal:2',
            'base_cost' => 'decimal:2',
            'fuel_surcharge' => 'decimal:2',
            'minimum_adjustment' => 'decimal:2',
            'net_total' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
