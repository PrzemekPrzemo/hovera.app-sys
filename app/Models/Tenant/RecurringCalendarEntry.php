<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\CalendarEntryType;
use App\Enums\RecurrencePattern;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringCalendarEntry extends TenantModel
{
    use SoftDeletes;

    protected $table = 'recurring_calendar_entries';

    protected $fillable = [
        'name', 'type',
        'starts_time', 'duration_minutes',
        'horse_id', 'instructor_id', 'arena_id', 'client_id',
        'recurrence_pattern', 'recurrence_interval',
        'recurrence_days_of_week',
        'recurrence_starts_on', 'recurrence_ends_on',
        'max_occurrences',
        'title', 'notes', 'price_cents', 'metadata',
        'is_active', 'created_by_central_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_time' => 'datetime:H:i:s',
            'duration_minutes' => 'integer',
            'recurrence_pattern' => RecurrencePattern::class,
            'recurrence_interval' => 'integer',
            'recurrence_days_of_week' => 'array',
            'recurrence_starts_on' => 'date',
            'recurrence_ends_on' => 'date',
            'max_occurrences' => 'integer',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'price_cents' => 'integer',
            'type' => CalendarEntryType::class,
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(CalendarEntry::class, 'recurrence_id');
    }
}
