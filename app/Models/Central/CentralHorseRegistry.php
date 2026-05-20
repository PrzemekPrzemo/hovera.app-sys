<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centralny rejestr koni — source of truth dla cross-tenant
 * identyfikacji konia. Patrz docs/MARKETPLACE-ROADMAP.md PR 4/5.
 *
 * Każdy `tenant.horses` row ma soft FK przez `central_horse_id`. Pojedynczy
 * koń może istnieć w kilku tenant DB jednocześnie (np. owner ma swoją
 * kartotekę + stajnia w której boarduje ma snapshot) — wszystkie te
 * lokalne projekcje wskazują na ten sam central_horse_registry.id.
 */
class CentralHorseRegistry extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'central_horse_registry';

    protected $fillable = [
        'primary_owner_user_id',
        'name',
        'breed',
        'dob',
        'passport_no',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_owner_user_id');
    }

    public function boardingAssignments(): HasMany
    {
        return $this->hasMany(HorseBoardingAssignment::class, 'central_horse_id');
    }

    /**
     * Aktywne boarding'i tego konia. W normalnych warunkach: max 1
     * (koń jest w jednej stajni). Multiple active = bug / dispute.
     */
    public function activeBoardingAssignments(): HasMany
    {
        return $this->hasMany(HorseBoardingAssignment::class, 'central_horse_id')
            ->where('status', 'active');
    }
}
