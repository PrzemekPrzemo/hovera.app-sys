<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Instructor extends TenantModel
{
    use SoftDeletes;

    protected $table = 'instructors';

    protected $fillable = [
        'central_user_id', 'name', 'email', 'phone',
        'hourly_rate_cents', 'color',
        'notes', 'is_active',
        'ics_token',
    ];

    /**
     * Return the existing ics token or generate one (and persist it)
     * the first time the instructor needs it. The Filament action that
     * exposes the feed URL calls this — lazy provisioning keeps the
     * column NULLable for backfilled rows that haven't been used yet.
     */
    public function ensureIcsToken(): string
    {
        if (! $this->ics_token) {
            $this->ics_token = Str::random(48);
            $this->save();
        }

        return (string) $this->ics_token;
    }

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
