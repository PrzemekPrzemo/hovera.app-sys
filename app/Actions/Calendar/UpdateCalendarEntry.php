<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Tenant\CalendarEntry;
use App\Services\Calendar\ConflictDetector;
use App\Services\Calendar\PassUseManager;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;

class UpdateCalendarEntry
{
    public function __construct(
        private readonly ConflictDetector $conflicts,
        private readonly TenantAuditLogger $audit,
        private readonly PassUseManager $passes,
    ) {}

    /**
     * @param  array<string,mixed>  $changes  any subset of fillable
     */
    public function execute(CalendarEntry $entry, array $changes): CalendarEntry
    {
        $previousStatus = $entry->status;

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

        $this->reconcilePassUseAfterStatusChange($entry, $previousStatus);

        return $entry;
    }

    /**
     * Pass-use reconciliation matrix:
     *
     *   was confirmed/completed  →  cancelled       try restoreFor()
     *   was confirmed/completed  →  no_show         keep use (it WAS owed)
     *   was cancelled/no_show    →  confirmed       applyTo() fresh
     *
     * Anything else (no status change, or completed→completed) → no-op.
     */
    private function reconcilePassUseAfterStatusChange(CalendarEntry $entry, CalendarEntryStatus $previousStatus): void
    {
        if (! $entry->client_id) {
            return;
        }
        if (! in_array($entry->type, [CalendarEntryType::LessonIndividual, CalendarEntryType::LessonGroup], true)) {
            return;
        }

        $newStatus = $entry->status;
        if ($newStatus === $previousStatus) {
            return;
        }

        $wasBlocking = $previousStatus->blocksResources();
        $isBlocking = $newStatus->blocksResources();

        // Now-cancelled (or no_show), was active.
        if ($wasBlocking && ! $isBlocking) {
            // No-show explicitly does not restore — the slot was held,
            // costs incurred. Only honest cancellations have a chance.
            if ($newStatus === CalendarEntryStatus::Cancelled) {
                $restored = $this->passes->restoreFor($entry, 'cancellation');
                $this->audit->record(
                    $restored ? 'pass.restored' : 'pass.cancellation_late',
                    'CalendarEntry',
                    (string) $entry->getKey(),
                );
            }

            return;
        }

        // Re-activated booking that previously had its use restored.
        if (! $wasBlocking && $isBlocking) {
            $use = $this->passes->applyTo($entry);
            if ($use) {
                $this->audit->record(
                    'pass.consumed',
                    'PassUse',
                    (string) $use->getKey(),
                    ['pass_id' => $use->pass_id, 'calendar_entry_id' => $entry->id, 'reactivation' => true],
                );
            }
        }
    }
}
