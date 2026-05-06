<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\Tenant\CalendarEntry;
use App\Services\Calendar\ConflictDetector;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;

class UpdateCalendarEntry
{
    public function __construct(
        private readonly ConflictDetector $conflicts,
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @param  array<string,mixed>  $changes  any subset of fillable
     */
    public function execute(CalendarEntry $entry, array $changes): CalendarEntry
    {
        $entry->fill($changes);

        // If time / resources changed, re-check conflicts (excluding self).
        if ($entry->isDirty(['starts_at', 'ends_at', 'horse_id', 'instructor_id', 'arena_id', 'status'])) {
            $startsAt = $entry->starts_at instanceof Carbon ? $entry->starts_at : Carbon::parse($entry->starts_at);
            $endsAt = $entry->ends_at instanceof Carbon ? $entry->ends_at : Carbon::parse($entry->ends_at);

            // Only validate conflicts if the new status would actually
            // occupy resources — moving to "cancelled" can't conflict.
            $statusBlocks = $entry->status->blocksResources();

            if ($statusBlocks) {
                $conflicts = $this->conflicts->forProposedEntry(
                    $entry->horse_id,
                    $entry->instructor_id,
                    $entry->arena_id,
                    $startsAt,
                    $endsAt,
                    ignoreEntryId: (string) $entry->getKey(),
                );

                if ($this->conflicts->hasAnyConflict($conflicts)) {
                    throw new CalendarConflictException($conflicts);
                }
            }
        }

        $changedFields = array_keys($entry->getDirty());
        $entry->save();

        if ($changedFields) {
            $this->audit->record(
                'calendar.update',
                'CalendarEntry',
                (string) $entry->getKey(),
                ['changed' => $changedFields],
            );
        }

        return $entry;
    }
}
