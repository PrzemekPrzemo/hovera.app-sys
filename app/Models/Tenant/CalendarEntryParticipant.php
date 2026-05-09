<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One participant of a group lesson — a (client, horse, attendance)
 * triple linked to a CalendarEntry. For type=LessonGroup this pivot is
 * the source of truth; the parent entry's `client_id`/`horse_id` are
 * left NULL.
 *
 * For other entry types (LessonIndividual, Training, Care, etc.) the
 * pivot is unused — those keep the simple scalar columns on
 * CalendarEntry.
 */
class CalendarEntryParticipant extends TenantModel
{
    public const ATTENDANCE_EXPECTED = 'expected';

    public const ATTENDANCE_PRESENT = 'present';

    public const ATTENDANCE_ABSENT = 'absent';

    public const ATTENDANCE_LATE = 'late';

    /** @return list<string> */
    public static function attendanceStatuses(): array
    {
        return [
            self::ATTENDANCE_EXPECTED,
            self::ATTENDANCE_PRESENT,
            self::ATTENDANCE_ABSENT,
            self::ATTENDANCE_LATE,
        ];
    }

    protected $table = 'calendar_entry_participants';

    protected $fillable = [
        'calendar_entry_id',
        'client_id', 'horse_id',
        'attendance_status',
        'notes',
    ];

    public function calendarEntry(): BelongsTo
    {
        return $this->belongsTo(CalendarEntry::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }
}
