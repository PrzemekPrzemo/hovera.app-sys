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
 * Wątek właściciel konia ↔ external specialist (PR O5 Channel D, epic 3).
 *
 * Cross-tenant, central DB. `horse_id` to jawnie udostępniony koń (vet
 * widzi tylko jego — per captured decisions §4).
 *
 * @property string $id
 * @property string $owner_user_id
 * @property string $specialist_id
 * @property string|null $horse_id
 * @property string $subject
 * @property Carbon|null $last_message_at
 */
class OwnerSpecialistThread extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'owner_specialist_threads';

    protected $fillable = [
        'owner_user_id', 'specialist_id', 'horse_id',
        'subject', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(ExternalSpecialist::class, 'specialist_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OwnerSpecialistMessage::class, 'thread_id')->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(OwnerSpecialistMessage::class, 'thread_id')->latestOfMany();
    }

    /** @param Builder<OwnerSpecialistThread> $query */
    public function scopeForOwner(Builder $query, string $ownerUserId): Builder
    {
        return $query->where('owner_user_id', $ownerUserId);
    }

    /** @param Builder<OwnerSpecialistThread> $query */
    public function scopeForSpecialist(Builder $query, string $specialistId): Builder
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function touchLastMessage(?Carbon $at = null): void
    {
        $this->forceFill(['last_message_at' => $at ?? now()])->save();
    }
}
