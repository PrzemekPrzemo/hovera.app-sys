<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Arena extends TenantModel
{
    use SoftDeletes;

    protected $table = 'arenas';

    protected $fillable = [
        'name', 'type', 'color',
        'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(CalendarEntry::class);
    }

    /**
     * @return array<string,string>
     */
    public static function typeOptions(): array
    {
        return [
            'indoor' => __('app/arena.types.indoor'),
            'outdoor' => __('app/arena.types.outdoor'),
            'paddock' => __('app/arena.types.paddock'),
            'lunge' => __('app/arena.types.lunge'),
            'field' => __('app/arena.types.field'),
        ];
    }
}
