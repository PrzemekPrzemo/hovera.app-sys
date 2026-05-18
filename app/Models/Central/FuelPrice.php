<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FuelPrice extends Model
{
    public const TYPE_DIESEL = 'diesel';

    public const TYPE_PETROL_95 = 'petrol_95';

    public const TYPE_PETROL_98 = 'petrol_98';

    public const TYPE_LPG = 'lpg';

    public const SOURCE_EPETROL = 'epetrol';

    public const SOURCE_MANUAL = 'manual';

    public $timestamps = false;

    protected $connection = 'central';

    protected $table = 'fuel_prices';

    protected $fillable = [
        'fuel_type', 'price_pln', 'snapshot_date', 'source', 'raw_payload', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'price_pln' => 'decimal:2',
            // Trzymamy snapshot_date jako string (bez Carbon cast) — `date` cast
            // generuje serializację 'Y-m-d 00:00:00' przy save, a lookup w
            // updateOrCreate idzie z surowym 'Y-m-d'. Mismatch → unique constraint
            // wybucha przy idempotent retry tego samego dnia. String round-trip
            // jest deterministyczny.
            'raw_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeOfType(Builder $q, string $fuelType): Builder
    {
        return $q->where('fuel_type', $fuelType);
    }
}
