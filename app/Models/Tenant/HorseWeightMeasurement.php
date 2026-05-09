<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodic body-weight tracking for a horse. Owners typically log
 * monthly so the trend (gain/loss/plateau) is visible at a glance.
 */
class HorseWeightMeasurement extends TenantModel
{
    protected $table = 'horse_weight_measurements';

    protected $fillable = [
        'horse_id',
        'measured_at', 'weight_kg', 'girth_cm',
        'notes',
        'measured_by_central_user_id',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'weight_kg' => 'decimal:1',
            'girth_cm' => 'decimal:1',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }
}
