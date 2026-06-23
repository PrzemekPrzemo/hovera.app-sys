<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Wiadomość w wątku Channel D (PR O5 epic 3).
 *
 * @property string $id
 * @property string $thread_id
 * @property string $sender_type
 * @property string $sender_id
 * @property string $body
 * @property array<int,array<string,mixed>>|null $attachments
 * @property Carbon|null $read_at
 */
class OwnerSpecialistMessage extends Model
{
    use HasUlids;

    public const SENDER_OWNER = 'owner';

    public const SENDER_SPECIALIST = 'specialist';

    protected $connection = 'central';

    protected $table = 'owner_specialist_messages';

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
        return $this->belongsTo(OwnerSpecialistThread::class, 'thread_id');
    }

    public function isFromSpecialist(): bool
    {
        return $this->sender_type === self::SENDER_SPECIALIST;
    }

    public function isFromOwner(): bool
    {
        return $this->sender_type === self::SENDER_OWNER;
    }

    /**
     * Nieprzeczytane wiadomości dla danej strony (przeciwnej do autora).
     *
     * @param Builder<OwnerSpecialistMessage> $query
     */
    public function scopeUnreadFor(Builder $query, string $recipientType): Builder
    {
        $authorType = $recipientType === self::SENDER_SPECIALIST
            ? self::SENDER_OWNER
            : self::SENDER_SPECIALIST;

        return $query->where('sender_type', $authorType)->whereNull('read_at');
    }
}
