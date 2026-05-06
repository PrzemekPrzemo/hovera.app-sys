<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Central\Tenant;
use App\Models\Tenant\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes free booking slots for the public booking flow.
 *
 *   - Working window per stable (settings.public_booking)
 *   - Excludes any existing instructor conflicts (resource-aware via
 *     ConflictDetector)
 *   - Respects advance_min_hours (anti-last-minute) and advance_max_days
 *     (anti-far-future)
 *
 * Slot duration = settings.public_booking.lesson_duration_minutes,
 * default 60. Slots are aligned to the same step (so 09:00, 10:00,
 * 11:00 — not arbitrary).
 */
class PublicBookingAvailability
{
    public function __construct(private readonly ConflictDetector $conflicts) {}

    /**
     * @return array{
     *     enabled: bool,
     *     advance_min_hours: int,
     *     advance_max_days: int,
     *     lesson_duration_minutes: int,
     *     working_hours_start: string,
     *     working_hours_end: string,
     * }
     */
    public function settingsFor(Tenant $tenant): array
    {
        $public = (array) (data_get($tenant->settings, 'public_booking') ?? []);

        return [
            'enabled' => (bool) ($public['enabled'] ?? false),
            'advance_min_hours' => (int) ($public['advance_min_hours'] ?? 4),
            'advance_max_days' => (int) ($public['advance_max_days'] ?? 30),
            'lesson_duration_minutes' => (int) ($public['lesson_duration_minutes'] ?? 60),
            'working_hours_start' => (string) ($public['working_hours_start'] ?? '09:00'),
            'working_hours_end' => (string) ($public['working_hours_end'] ?? '19:00'),
        ];
    }

    /**
     * Free start times for a given instructor on a given date.
     *
     * @return Collection<int, Carbon>
     */
    public function slotsFor(Tenant $tenant, Instructor $instructor, Carbon $date): Collection
    {
        $cfg = $this->settingsFor($tenant);
        if (! $cfg['enabled']) {
            return collect();
        }

        $dayStart = $date->copy()->setTimeFromTimeString($cfg['working_hours_start'].':00');
        $dayEnd = $date->copy()->setTimeFromTimeString($cfg['working_hours_end'].':00');

        $earliest = now()->copy()->addHours($cfg['advance_min_hours']);
        $latest = now()->copy()->addDays($cfg['advance_max_days'])->endOfDay();

        if ($dayEnd->lte($earliest) || $dayStart->gt($latest)) {
            return collect();
        }

        $duration = max(15, $cfg['lesson_duration_minutes']);

        $slots = collect();
        $cursor = $dayStart->copy();
        while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($duration);

            // Below the earliest allowed start? skip
            if ($slotStart->lt($earliest)) {
                $cursor->addMinutes($duration);

                continue;
            }
            // Past the latest allowed start? stop
            if ($slotStart->gt($latest)) {
                break;
            }

            $instructorBusy = $this->conflicts
                ->forInstructor((string) $instructor->id, $slotStart, $slotEnd)
                ->isNotEmpty();

            if (! $instructorBusy) {
                $slots->push($slotStart);
            }

            $cursor->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * Returns the dates in the next N days that have at least one slot.
     * Used to pre-populate a "pick a day" view so the user doesn't click
     * into empty days.
     *
     * @return Collection<int, string>  ISO date strings
     */
    public function datesWithSlots(Tenant $tenant, Instructor $instructor): Collection
    {
        $cfg = $this->settingsFor($tenant);
        if (! $cfg['enabled']) {
            return collect();
        }

        $start = now()->copy()->startOfDay();
        $end = now()->copy()->addDays($cfg['advance_max_days'])->endOfDay();

        $dates = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($this->slotsFor($tenant, $instructor, $cursor)->isNotEmpty()) {
                $dates->push($cursor->toDateString());
            }
            $cursor->addDay();
        }

        return $dates;
    }
}
