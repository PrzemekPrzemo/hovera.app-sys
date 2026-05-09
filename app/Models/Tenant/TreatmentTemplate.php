<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\HealthRecordType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable preset for HealthRecord — picks type, suggests next_due
 * based on its own interval_days, pre-fills summary + notes.
 *
 * Standard PL templates (vaccinations, farrier, dental) are seeded
 * by the table migration so every tenant starts with sensible defaults.
 * Owners can disable / edit them or add custom ones.
 */
class TreatmentTemplate extends TenantModel
{
    protected $table = 'treatment_templates';

    protected $fillable = [
        'name', 'type', 'interval_days',
        'default_summary', 'default_notes',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => HealthRecordType::class,
            'interval_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
