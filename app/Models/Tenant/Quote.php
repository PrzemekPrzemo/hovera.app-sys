<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\CalculationMode;
use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends TenantModel
{
    use SoftDeletes;

    protected $table = 'quotes';

    protected $fillable = [
        'number', 'status',
        'customer_id',
        'customer_name', 'customer_email', 'customer_phone',
        'customer_company', 'customer_tax_id', 'customer_address',
        'pickup_address', 'pickup_lat', 'pickup_lng',
        'dropoff_address', 'dropoff_lat', 'dropoff_lng',
        'preferred_date', 'preferred_time', 'round_trip', 'calculation_mode', 'loaded',
        'horses_count',
        'vehicle_id', 'trailer_id', 'driver_id',
        'distance_km', 'duration_seconds', 'routing_provider', 'polyline',
        'rate_per_km', 'base_cost', 'fuel_surcharge', 'extra_horse_fee_snapshot',
        'fixed_fees_snapshot', 'surcharge_percent_snapshot', 'surcharge_amount_snapshot',
        'minimum_adjustment',
        'net_total', 'vat_rate', 'vat_amount', 'gross_total', 'currency',
        'exchange_rate_to_pln', 'exchange_rate_date',
        'terms', 'notes', 'valid_until',
        'accept_token',
        'sent_at', 'accepted_at', 'rejected_at', 'expired_at', 'withdrawn_at',
        'lead_id', 'response_id',
        'pdf_url',
        'payment_url', 'payment_method_label', 'payment_completed_at', 'payment_notes',
        'p24_session_id', 'p24_payment_url', 'p24_order_id', 'p24_paid_at',
        'payu_order_id', 'payu_ext_order_id', 'payu_payment_url', 'payu_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'calculation_mode' => CalculationMode::class,
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'preferred_date' => 'date',
            'valid_until' => 'date',
            'round_trip' => 'boolean',
            'loaded' => 'boolean',
            'horses_count' => 'integer',
            'distance_km' => 'decimal:2',
            'duration_seconds' => 'integer',
            'rate_per_km' => 'decimal:2',
            'base_cost' => 'decimal:2',
            'fuel_surcharge' => 'decimal:2',
            'extra_horse_fee_snapshot' => 'decimal:2',
            'fixed_fees_snapshot' => 'array',
            'surcharge_percent_snapshot' => 'decimal:2',
            'surcharge_amount_snapshot' => 'decimal:2',
            'minimum_adjustment' => 'decimal:2',
            'net_total' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'exchange_rate_to_pln' => 'decimal:4',
            'exchange_rate_date' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'payment_completed_at' => 'datetime',
            'p24_paid_at' => 'datetime',
            'payu_paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Opcjonalna przyczepa kombinowana z `vehicle` (np. samochód + przyczepa
     * do koni). Cel: w ofercie pokazać klientowi że jedziemy z konkretną
     * przyczepą o ileś-koniach miejscach. Calculator NIE łączy spalania
     * (przyczepa = bez silnika, paliwo liczy się od `vehicle`).
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'trailer_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
