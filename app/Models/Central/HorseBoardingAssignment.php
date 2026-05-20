<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Boarding'owy łącznik koń ↔ stajnia. Cross-tenant — żyje w central
 * DB, bo dotyczy DWÓCH różnych tenantów (owner + stable).
 * Patrz docs/MARKETPLACE-ROADMAP.md PR 4/5.
 *
 * Lifecycle:
 *   pending  → owner zapytał, stable jeszcze nie zaakceptował
 *   active   → boarding aktywny
 *   ended    → boarding zakończony (historyczny rekord)
 *   disputed → konflikt, admin manual review
 *
 * Unique constraint na (central_horse_id, stable_tenant_id, status)
 * zapobiega duplikatom w tym samym statusie ale pozwala na historię
 * (kilka 'ended' + jeden 'active').
 */
class HorseBoardingAssignment extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_DISPUTED = 'disputed';

    protected $connection = 'central';

    protected $table = 'horse_boarding_assignments';

    protected $fillable = [
        'central_horse_id',
        'stable_tenant_id',
        'owner_user_id',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(CentralHorseRegistry::class, 'central_horse_id');
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'stable_tenant_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForStable(Builder $query, string $tenantId): Builder
    {
        return $query->where('stable_tenant_id', $tenantId);
    }
}
