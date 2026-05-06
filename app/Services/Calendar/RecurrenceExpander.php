<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\RecurrencePattern;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\RecurringCalendarEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Expands a recurring template into a list of (date, occurrence_index)
 * pairs that should exist as concrete calendar_entries.
 *
 * Idempotent — calling it twice for the same series and the same target
 * window produces the same result, and dates that already have an entry
 * with this `recurrence_id` are excluded so we never double-create.
 *
 * Hard-capped to MAX_OCCURRENCES_PER_EXPANSION so an "open-ended" series
 * (no end date, no max_occurrences) can't blow up the database. Default
 * cap is one year of weekly bookings.
 */
class RecurrenceExpander
{
    public const MAX_OCCURRENCES_PER_EXPANSION = 365;

    /**
     * @return Collection<int, array{date: Carbon, occurrence: int}>
     */
    public function expand(
        RecurringCalendarEntry $series,
        ?Carbon $until = null,
    ): Collection {
        $startsOn = Carbon::parse($series->recurrence_starts_on)->startOfDay();

        $effectiveEnd = $this->resolveEffectiveEnd($series, $until);
        if ($effectiveEnd->lt($startsOn)) {
            return collect();
        }

        $existing = $this->existingOccurrenceDates($series);
        $candidates = $this->generateCandidates($series, $startsOn, $effectiveEnd);

        $cap = $series->max_occurrences ?? self::MAX_OCCURRENCES_PER_EXPANSION;

        $result = collect();
        $occurrenceIndex = $existing->count() + 1;

        foreach ($candidates as $date) {
            if ($result->count() + $existing->count() >= $cap) {
                break;
            }
            if ($existing->contains(fn (string $d) => $d === $date->toDateString())) {
                continue;
            }

            $result->push([
                'date' => $date->copy(),
                'occurrence' => $occurrenceIndex++,
            ]);
        }

        return $result;
    }

    /**
     * Resolve the upper bound of expansion — explicit `$until`, the
     * series' `recurrence_ends_on`, or a sensible default (1 year out).
     */
    private function resolveEffectiveEnd(RecurringCalendarEntry $series, ?Carbon $until): Carbon
    {
        $candidates = array_filter([
            $until?->copy()->endOfDay(),
            $series->recurrence_ends_on
                ? Carbon::parse($series->recurrence_ends_on)->endOfDay()
                : null,
            Carbon::parse($series->recurrence_starts_on)->copy()->addYear()->endOfDay(),
        ]);

        // Min of the candidates — we stop at whichever comes soonest.
        usort($candidates, fn (Carbon $a, Carbon $b) => $a <=> $b);

        return $candidates[0];
    }

    /**
     * @return Collection<int, string>
     */
    private function existingOccurrenceDates(RecurringCalendarEntry $series): Collection
    {
        return CalendarEntry::query()
            ->where('recurrence_id', $series->id)
            ->pluck('starts_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function generateCandidates(RecurringCalendarEntry $series, Carbon $startsOn, Carbon $effectiveEnd): Collection
    {
        return match ($series->recurrence_pattern) {
            RecurrencePattern::Daily => $this->generateDaily($startsOn, $effectiveEnd, $series->recurrence_interval),
            RecurrencePattern::Weekly => $this->generateWeekly(
                $startsOn,
                $effectiveEnd,
                $series->recurrence_interval,
                (array) ($series->recurrence_days_of_week ?? []),
            ),
            RecurrencePattern::Monthly => $this->generateMonthly($startsOn, $effectiveEnd, $series->recurrence_interval),
        };
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function generateDaily(Carbon $start, Carbon $end, int $interval): Collection
    {
        $dates = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $cursor->addDays(max(1, $interval));
        }

        return $dates;
    }

    /**
     * @param  array<int,int>  $daysOfWeek  0..6 (0 = Sunday)
     * @return Collection<int, Carbon>
     */
    private function generateWeekly(Carbon $start, Carbon $end, int $interval, array $daysOfWeek): Collection
    {
        $daysOfWeek = array_map('intval', $daysOfWeek);
        if ($daysOfWeek === []) {
            // Fall back to "every interval-week on the start date's weekday"
            $daysOfWeek = [(int) $start->dayOfWeek];
        }

        $dates = collect();
        $weekCursor = $start->copy()->startOfWeek(Carbon::SUNDAY);
        while ($weekCursor->lte($end)) {
            foreach ($daysOfWeek as $dow) {
                $candidate = $weekCursor->copy()->addDays($dow);
                if ($candidate->between($start, $end)) {
                    $dates->push($candidate);
                }
            }
            $weekCursor->addWeeks(max(1, $interval));
        }

        return $dates->sortBy(fn (Carbon $d) => $d->timestamp)->values();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function generateMonthly(Carbon $start, Carbon $end, int $interval): Collection
    {
        $dates = collect();
        $cursor = $start->copy();
        $dayOfMonth = (int) $start->day;

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $next = $cursor->copy()->addMonthsNoOverflow(max(1, $interval));
            // Preserve the original day-of-month where the target month allows.
            $maxDay = (int) $next->copy()->endOfMonth()->day;
            $next->day = min($dayOfMonth, $maxDay);
            $cursor = $next;
        }

        return $dates;
    }
}
