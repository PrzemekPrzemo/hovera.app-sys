<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Tenant\CalendarEntry;
use Illuminate\Support\Collection;

/**
 * Finds calendar entries that conflict with a proposed time slot for a
 * given resource (horse / instructor / arena).
 *
 * "Conflict" means: another entry whose time window overlaps AND whose
 * status actually occupies the resource (cancelled and no-show entries
 * are ignored — those slots are free).
 *
 * Used by CreateCalendarEntry / UpdateCalendarEntry to validate and
 * by the calendar UI to surface "this would clash with…" previews.
 */
class ConflictDetector
{
    /**
     * @return Collection<int, CalendarEntry>
     */
    public function forHorse(string $horseId, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt, ?string $ignoreEntryId = null): Collection
    {
        return $this->resourceConflicts('horse_id', $horseId, $startsAt, $endsAt, $ignoreEntryId);
    }

    /**
     * @return Collection<int, CalendarEntry>
     */
    public function forInstructor(string $instructorId, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt, ?string $ignoreEntryId = null): Collection
    {
        return $this->resourceConflicts('instructor_id', $instructorId, $startsAt, $endsAt, $ignoreEntryId);
    }

    /**
     * @return Collection<int, CalendarEntry>
     */
    public function forArena(string $arenaId, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt, ?string $ignoreEntryId = null): Collection
    {
        return $this->resourceConflicts('arena_id', $arenaId, $startsAt, $endsAt, $ignoreEntryId);
    }

    /**
     * Run all three resource checks at once for a proposed entry.
     * Returns a structured array — empty arrays mean "no conflict".
     *
     * @return array{
     *     horse: Collection<int, CalendarEntry>,
     *     instructor: Collection<int, CalendarEntry>,
     *     arena: Collection<int, CalendarEntry>,
     * }
     */
    public function forProposedEntry(
        ?string $horseId,
        ?string $instructorId,
        ?string $arenaId,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        ?string $ignoreEntryId = null,
    ): array {
        return [
            'horse' => $horseId
                ? $this->forHorse($horseId, $startsAt, $endsAt, $ignoreEntryId)
                : collect(),
            'instructor' => $instructorId
                ? $this->forInstructor($instructorId, $startsAt, $endsAt, $ignoreEntryId)
                : collect(),
            'arena' => $arenaId
                ? $this->forArena($arenaId, $startsAt, $endsAt, $ignoreEntryId)
                : collect(),
        ];
    }

    public function hasAnyConflict(array $conflicts): bool
    {
        return $conflicts['horse']->isNotEmpty()
            || $conflicts['instructor']->isNotEmpty()
            || $conflicts['arena']->isNotEmpty();
    }

    private function resourceConflicts(
        string $foreignKey,
        string $resourceId,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        ?string $ignoreEntryId,
    ): Collection {
        return CalendarEntry::query()
            ->where($foreignKey, $resourceId)
            ->blockingResources()
            ->overlapping($startsAt, $endsAt)
            ->when($ignoreEntryId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get();
    }
}
