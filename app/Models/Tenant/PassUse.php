<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassUse extends TenantModel
{
    protected $table = 'pass_uses';

    protected $fillable = [
        'pass_id', 'calendar_entry_id',
        'consumed_at', 'restored_at', 'restored_reason',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function calendarEntry(): BelongsTo
    {
        return $this->belongsTo(CalendarEntry::class);
    }

    public function isActive(): bool
    {
        return $this->restored_at === null;
    }
}
