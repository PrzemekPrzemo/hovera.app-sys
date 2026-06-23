<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pojedyncza wiadomość w wątku Channel B (PR O5, epic 1.3).
 *
 * `sender_type`:
 *   - SENDER_TENANT_USER → sender_id = central user id
 *   - SENDER_SPECIALIST  → sender_id = external_specialists id
 *
 * @property string $id
 * @property string $thread_id
 * @property string $sender_type
 * @property string $sender_id
 * @property string $body
 * @property array<int,array<string,mixed>>|null $attachments
 * @property Carbon|null $read_at
 */
class SpecialistMessage extends Model
{
    use HasUlids;

    public const SENDER_TENANT_USER = 'tenant_user';

    public const SENDER_SPECIALIST = 'specialist';

    protected $connection = 'central';

    protected $table = 'specialist_messages';

    protected $fillable = [
        'thread_id', 'sender_type', 'sender_id',
        'body', 'attachments', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SpecialistThread::class, 'thread_id');
    }

    public function isFromSpecialist(): bool
    {
        return $this->sender_type === self::SENDER_SPECIALIST;
    }

    public function isFromTenantUser(): bool
    {
        return $this->sender_type === self::SENDER_TENANT_USER;
    }

    public function markRead(?Carbon $at = null): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill(['read_at' => $at ?? now()])->save();
    }

    /**
     * Nieprzeczytane wiadomości skierowane do danej strony (przeciwnej niż
     * `$senderType` autora) — używane do liczenia unread badge.
     *
     * @param Builder<SpecialistMessage> $query
     */
    public function scopeUnreadFor(Builder $query, string $recipientType): Builder
    {
        $authorType = $recipientType === self::SENDER_SPECIALIST
            ? self::SENDER_TENANT_USER
            : self::SENDER_SPECIALIST;

        return $query->where('sender_type', $authorType)->whereNull('read_at');
    }
}
