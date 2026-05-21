<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centralny model: request zmiany kluczowego pola konia (name /
 * passport_number / microchip). Stable proposed → Owner accept lub
 * reject. Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.3".
 */
class HorseFieldChangeRequest extends Model
{
    use HasUlids;

    public const FIELD_NAME = 'name';

    public const FIELD_PASSPORT = 'passport_number';

    public const FIELD_MICROCHIP = 'microchip';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const ALL_FIELDS = [
        self::FIELD_NAME,
        self::FIELD_PASSPORT,
        self::FIELD_MICROCHIP,
    ];

    protected $connection = 'central';

    protected $table = 'horse_field_change_requests';

    protected $fillable = [
        'central_horse_id',
        'field',
        'old_value',
        'new_value',
        'proposed_by_tenant_id',
        'proposed_by_user_id',
        'status',
        'proposed_at',
        'decided_at',
        'decided_by_user_id',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'proposed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(CentralHorseRegistry::class, 'central_horse_id');
    }

    public function proposedByTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'proposed_by_tenant_id');
    }

    public function proposedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForHorse(Builder $query, string $centralHorseId): Builder
    {
        return $query->where('central_horse_id', $centralHorseId);
    }
}
