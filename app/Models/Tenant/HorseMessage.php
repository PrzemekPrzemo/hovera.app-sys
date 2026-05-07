<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorseMessage extends TenantModel
{
    protected $table = 'horse_messages';

    protected $fillable = [
        'horse_id', 'direction', 'sender_user_id', 'client_id',
        'subject', 'body', 'attachments', 'sent_at',
        'read_by_client_at', 'read_by_stable_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
            'read_by_client_at' => 'datetime',
            'read_by_stable_at' => 'datetime',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isFromStable(): bool
    {
        return $this->direction === 'from_stable';
    }

    public function isFromClient(): bool
    {
        return $this->direction === 'from_client';
    }

    /** Czy wiadomość jest nieprzeczytana z perspektywy klienta. */
    public function isUnreadByClient(): bool
    {
        return $this->isFromStable() && $this->read_by_client_at === null;
    }

    /** Czy wiadomość jest nieprzeczytana z perspektywy stajni. */
    public function isUnreadByStable(): bool
    {
        return $this->isFromClient() && $this->read_by_stable_at === null;
    }

    /** Liczba załączników. */
    public function attachmentCount(): int
    {
        return is_array($this->attachments) ? count($this->attachments) : 0;
    }

    public function scopeForHorse(Builder $query, string $horseId): Builder
    {
        return $query->where('horse_id', $horseId);
    }

    public function scopeForClient(Builder $query, string $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeUnreadByClient(Builder $query): Builder
    {
        return $query->where('direction', 'from_stable')->whereNull('read_by_client_at');
    }

    public function scopeUnreadByStable(Builder $query): Builder
    {
        return $query->where('direction', 'from_client')->whereNull('read_by_stable_at');
    }
}
