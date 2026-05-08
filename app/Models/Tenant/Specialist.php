<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-stable contact entry for a vet (`type=vet`) or farrier
 * (`type=farrier`). Optionally linked to a central User via
 * `central_user_id` when the specialist is a Hovera-account holder
 * (i.e. an employee that also rides as a vet/farrier and needs to
 * see their own tasks in /app).
 */
class Specialist extends TenantModel
{
    use SoftDeletes;

    protected $table = 'specialists';

    public const TYPE_VET = 'vet';

    public const TYPE_FARRIER = 'farrier';

    protected $fillable = [
        'type', 'central_user_id', 'name', 'email', 'phone',
        'color', 'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Health records performed by this specialist (vet visits, farrier
     * work, dental — depending on type). Backref via FK on health_records.
     */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StableActivity::class);
    }

    public function scopeVets(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_VET);
    }

    public function scopeFarriers(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_FARRIER);
    }

    public function isVet(): bool
    {
        return $this->type === self::TYPE_VET;
    }

    public function isFarrier(): bool
    {
        return $this->type === self::TYPE_FARRIER;
    }
}
