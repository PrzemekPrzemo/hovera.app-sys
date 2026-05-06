<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructor extends TenantModel
{
    use SoftDeletes;

    protected $table = 'instructors';

    protected $fillable = [
        'central_user_id', 'name', 'email', 'phone',
        'hourly_rate_cents', 'color',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hourly_rate_cents' => 'integer',
        ];
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(CalendarEntry::class);
    }
}
