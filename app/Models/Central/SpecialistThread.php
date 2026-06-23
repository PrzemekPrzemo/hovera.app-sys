<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Wątek wiadomości stajnia ↔ external specialist (PR O5 Channel B, epic 1.3).
 *
 * Cross-tenant: żyje w central DB. Każdy wątek to para (tenant, specialist)
 * z opcjonalnym kontekstem konia (`horse_id` — soft ref do tenant DB).
 *
 * @property string $id
 * @property string $specialist_id
 * @property string $tenant_id
 * @property string|null $horse_id
 * @property string $subject
 * @property Carbon|null $last_message_at
 */
class SpecialistThread extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'specialist_threads';

    protected $fillable = [
        'specialist_id', 'tenant_id', 'horse_id',
        'subject', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(ExternalSpecialist::class, 'specialist_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SpecialistMessage::class, 'thread_id')->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SpecialistMessage::class, 'thread_id')->latestOfMany();
    }

    /** @param Builder<SpecialistThread> $query */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @param Builder<SpecialistThread> $query */
    public function scopeForSpecialist(Builder $query, string $specialistId): Builder
    {
        return $query->where('specialist_id', $specialistId);
    }

    /**
     * Bumpuje `last_message_at` (denormalizacja do sortowania listy).
     */
    public function touchLastMessage(?Carbon $at = null): void
    {
        $this->forceFill(['last_message_at' => $at ?? now()])->save();
    }
}
