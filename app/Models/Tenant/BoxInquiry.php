<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;

/**
 * Publiczne zapytanie o rezerwację boksu. Przychodzi z `/s/{slug}/box-inquiry`
 * (formularz na public micro-site albo embed widget). Stable obsługuje
 * w panelu — kontakt, zamknięcie, ew. konwersja do `Client` + `Box`.
 */
class BoxInquiry extends TenantModel
{
    protected $table = 'box_inquiries';

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SPAM = 'spam';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_CLOSED,
        self::STATUS_SPAM,
    ];

    public const SOURCE_EMBED = 'embed';

    public const SOURCE_PUBLIC_SITE = 'public_site';

    protected $fillable = [
        'name', 'email', 'phone',
        'horse_count', 'preferred_from', 'message',
        'status', 'responded_at', 'responded_by_user_id', 'response_notes',
        'source', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'preferred_from' => 'date',
            'responded_at' => 'datetime',
            'horse_count' => 'integer',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_CONTACTED]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_CONTACTED], true);
    }
}
