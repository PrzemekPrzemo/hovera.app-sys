<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Członkostwo w kanale wewnętrznym (PR O5 Channel C, epic 2).
 *
 * Composite PK (channel_id, user_id) — brak własnego `id`. `user_id` to
 * central user id (soft ref). `last_read_at` napędza unread badge + digest.
 *
 * @property string $channel_id
 * @property string $user_id
 * @property Carbon $joined_at
 * @property bool $notifications_enabled
 * @property Carbon|null $last_read_at
 */
class InternalChannelMember extends Model
{
    protected $connection = 'tenant';

    protected $table = 'internal_channel_members';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = [
        'channel_id', 'user_id', 'joined_at', 'notifications_enabled', 'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
            'notifications_enabled' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(InternalChannel::class, 'channel_id');
    }
}
