<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Services\Calendar\ConflictDetector;
use App\Services\Calendar\PassUseManager;
use App\Services\Calendar\RecurrenceExpander;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Materialise a recurring template into concrete calendar_entries.
 *
 * Skip-on-conflict: if a candidate occurrence collides with another
 * resource booking, that single date is skipped (and reported back to
 * the caller) rather than aborting the whole series.
 *
 * Returns a summary so the UI can show "Utworzono X z Y, pominięto Z
 * z powodu konfliktu" without asking the database again.
 */
class CreateRecurringSeries
{
    public function __construct(
        private readonly RecurrenceExpander $expander,
        private readonly ConflictDetector $conflicts,
        private readonly PassUseManager $passes,
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @return array{
     *     series: RecurringCalendarEntry,
     *     created: int,
     *     skipped_conflicts: array<int, string>,
     * }
     */
    public function execute(RecurringCalendarEntry $series, ?Carbon $until = null): array
    {
        $candidates = $this->expander->expand($series, $until);

        $created = 0;
        $skipped = [];

        foreach ($candidates as $candidate) {
            /** @var Carbon $date */
            $date = $candidate['date'];
            $occurrenceIndex = $candidate['occurrence'];

            $startsAt = $date->copy()->setTimeFromTimeString(
                $series->starts_time instanceof \DateTimeInterface
                    ? $series->starts_time->format('H:i:s')
                    : (string) $series->starts_time
            );
            $endsAt = $startsAt->copy()->addMinutes($series->duration_minutes);

            $hasConflict = $this->conflicts->hasAnyConflict(
                $this->conflicts->forProposedEntry(
                    horseId: $series->horse_id,
                    instructorId: $series->instructor_id,
                    arenaId: $series->arena_id,
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                ),
            );

            if ($hasConflict) {
                $skipped[] = $date->toDateString();

                continue;
            }

            DB::connection('tenant')->transaction(function () use (
                $series, $startsAt, $endsAt, $occurrenceIndex
            ) {
                $entry = CalendarEntry::create([
                    'type' => $series->type->value,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'horse_id' => $series->horse_id,
                    'instructor_id' => $series->instructor_id,
                    'arena_id' => $series->arena_id,
                    'client_id' => $series->client_id,
                    'recurrence_id' => $series->id,
                    'recurrence_occurrence' => $occurrenceIndex,
                    'status' => CalendarEntryStatus::Confirmed->value,
                    'title' => $series->title,
                    'notes' => $series->notes,
                    'price_cents' => $series->price_cents,
                    'created_by_central_user_id' => Auth::id(),
                ]);

                // Try to consume a pass — silently no-ops if there's none.
                $this->passes->applyTo($entry);
            });

            $created++;
        }

        $this->audit->record(
            'recurrence.expanded',
            'RecurringCalendarEntry',
            (string) $series->getKey(),
            ['created' => $created, 'skipped' => count($skipped)],
        );

        return [
            'series' => $series,
            'created' => $created,
            'skipped_conflicts' => $skipped,
        ];
    }
}
