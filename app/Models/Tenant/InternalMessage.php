<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wiadomość w kanale wewnętrznym (PR O5 Channel C, epic 2).
 *
 * `mentions` to lista central user id wyłuskanych z `@nick` w treści —
 * używana do targetowanych notyfikacji.
 *
 * @property string $id
 * @property string $channel_id
 * @property string $author_user_id
 * @property string $body
 * @property array<int,array<string,mixed>>|null $attachments
 * @property array<int,string>|null $mentions
 */
class InternalMessage extends TenantModel
{
    protected $table = 'internal_messages';

    protected $fillable = [
        'channel_id', 'author_user_id', 'body', 'attachments', 'mentions',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'mentions' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(InternalChannel::class, 'channel_id');
    }
}
