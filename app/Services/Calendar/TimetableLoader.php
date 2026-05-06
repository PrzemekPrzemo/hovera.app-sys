<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\CalendarEntryType;
use App\Models\Tenant\Arena;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Loads CalendarEntry rows for a day, sliced into lanes (one column
 * per instructor / arena / horse / "all"), and decorates each entry
 * with positioning hints used by the Blade timetable.
 *
 *   ┌─ Instruktor A ─┬─ Instruktor B ─┬─ Instruktor C ─┐
 *   │                │                │                │
 *   │  09:00 ┌────┐  │                │                │
 *   │  10:00 │ A  │  │  09:30 ┌────┐  │                │
 *   │  11:00 └────┘  │  10:30 │ B  │  │                │
 *   │                │  11:30 └────┘  │                │
 *
 * Positioning is "minutes from `view_start`" so the Blade view can
 * just `style="top: {$top}px; height: {$height}px"` (1 minute = 1 px,
 * configurable).
 */
class TimetableLoader
{
    private const DEFAULT_VIEW_START_HOUR = 6;

    private const DEFAULT_VIEW_END_HOUR = 22;

    public const MINUTE_HEIGHT_PX = 1;

    /**
     * @param  string  $groupBy  one of: instructor, arena, horse, none
     * @param  string|null  $typeFilter  CalendarEntryType value or null
     * @return array{
     *     date: string,
     *     view_start: Carbon,
     *     view_end: Carbon,
     *     view_minutes: int,
     *     lanes: array<int, array{id:?string, label:string, color:?string, entries: array<int, array<string,mixed>>}>,
     * }
     */
    public function loadDay(
        Carbon $date,
        string $groupBy = 'none',
        ?string $typeFilter = null,
        int $viewStartHour = self::DEFAULT_VIEW_START_HOUR,
        int $viewEndHour = self::DEFAULT_VIEW_END_HOUR,
    ): array {
        $viewStart = $date->copy()->setTime($viewStartHour, 0);
        $viewEnd = $date->copy()->setTime($viewEndHour, 0);
        $viewMinutes = (int) $viewStart->diffInMinutes($viewEnd);

        $entries = CalendarEntry::query()
            ->with(['horse', 'instructor', 'arena', 'client'])
            ->overlapping($viewStart, $viewEnd)
            ->blockingResources()
            ->when($typeFilter, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('starts_at')
            ->get();

        $lanes = $this->buildLanes($groupBy, $entries, $viewStart, $viewEnd);

        return [
            'date' => $date->toDateString(),
            'view_start' => $viewStart,
            'view_end' => $viewEnd,
            'view_minutes' => $viewMinutes,
            'lanes' => $lanes,
        ];
    }

    /**
     * @return array<int, array{id:?string, label:string, color:?string, entries: array<int, array<string,mixed>>}>
     */
    private function buildLanes(string $groupBy, Collection $entries, Carbon $viewStart, Carbon $viewEnd): array
    {
        if ($groupBy === 'none') {
            return [[
                'id' => null,
                'label' => 'Wszystkie rezerwacje',
                'color' => null,
                'entries' => $entries->map(fn (CalendarEntry $e) => $this->decorateEntry($e, $viewStart, $viewEnd))->all(),
            ]];
        }

        // For per-resource grouping, we list every active resource as a
        // lane (even ones with no events today) — empty columns make
        // it obvious where there's free capacity.
        return match ($groupBy) {
            'instructor' => $this->groupByForeignKey(
                'instructor_id',
                Instructor::query()->where('is_active', true)->orderBy('name')->get(),
                $entries, $viewStart, $viewEnd,
            ),
            'arena' => $this->groupByForeignKey(
                'arena_id',
                Arena::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
                $entries, $viewStart, $viewEnd,
            ),
            'horse' => $this->groupByForeignKey(
                'horse_id',
                Horse::query()->orderBy('name')->get(),
                $entries, $viewStart, $viewEnd,
            ),
            default => $this->buildLanes('none', $entries, $viewStart, $viewEnd),
        };
    }

    /**
     * @return array<int, array{id:?string, label:string, color:?string, entries: array<int, array<string,mixed>>}>
     */
    private function groupByForeignKey(string $foreignKey, Collection $resources, Collection $entries, Carbon $viewStart, Carbon $viewEnd): array
    {
        $byResource = $entries->groupBy($foreignKey);
        $lanes = [];

        foreach ($resources as $resource) {
            $lanes[] = [
                'id' => $resource->id,
                'label' => $resource->name,
                'color' => $resource->color ?? null,
                'entries' => $byResource->get($resource->id, collect())
                    ->map(fn (CalendarEntry $e) => $this->decorateEntry($e, $viewStart, $viewEnd))
                    ->all(),
            ];
        }

        // Catch-all lane for entries with no resource assigned to this
        // grouping dimension (e.g. an event with no instructor).
        $orphans = $byResource->get(null, collect());
        if ($orphans->isNotEmpty()) {
            $lanes[] = [
                'id' => null,
                'label' => 'Bez przypisania',
                'color' => null,
                'entries' => $orphans->map(fn (CalendarEntry $e) => $this->decorateEntry($e, $viewStart, $viewEnd))->all(),
            ];
        }

        return $lanes;
    }

    /**
     * @return array<string, mixed>
     */
    private function decorateEntry(CalendarEntry $entry, Carbon $viewStart, Carbon $viewEnd): array
    {
        // Clamp to the visible window — entries that started before
        // view_start are rendered from the top edge, ones that run past
        // view_end are clipped at the bottom.
        $effectiveStart = $entry->starts_at->lt($viewStart) ? $viewStart : $entry->starts_at;
        $effectiveEnd = $entry->ends_at->gt($viewEnd) ? $viewEnd : $entry->ends_at;

        $topMinutes = (int) $viewStart->diffInMinutes($effectiveStart);
        $heightMinutes = max(15, (int) $effectiveStart->diffInMinutes($effectiveEnd));

        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'type_label' => $entry->type->label(),
            'status' => $entry->status,
            'starts_at' => $entry->starts_at,
            'ends_at' => $entry->ends_at,
            'starts_at_display' => $entry->starts_at->format('H:i'),
            'ends_at_display' => $entry->ends_at->format('H:i'),
            'horse' => $entry->horse?->name,
            'instructor' => $entry->instructor?->name,
            'arena' => $entry->arena?->name,
            'client' => $entry->client?->name,
            'title' => $entry->title,
            'top_px' => $topMinutes * self::MINUTE_HEIGHT_PX,
            'height_px' => $heightMinutes * self::MINUTE_HEIGHT_PX,
            'color' => $this->pickColor($entry),
            'is_clipped_top' => $entry->starts_at->lt($viewStart),
            'is_clipped_bottom' => $entry->ends_at->gt($viewEnd),
        ];
    }

    /**
     * Color resolution: arena.color → instructor.color → type fallback.
     * Stables typically tint by arena (so you can see "what's happening
     * in the indoor"), but we let either resource win.
     */
    private function pickColor(CalendarEntry $entry): string
    {
        if ($entry->arena?->color) {
            return $entry->arena->color;
        }
        if ($entry->instructor?->color) {
            return $entry->instructor->color;
        }

        return match ($entry->type) {
            CalendarEntryType::LessonIndividual => '#10b981',  // emerald
            CalendarEntryType::LessonGroup => '#06b6d4',       // cyan
            CalendarEntryType::Training => '#8b5cf6',          // violet
            CalendarEntryType::Care => '#f59e0b',              // amber
            CalendarEntryType::Event => '#ec4899',             // pink
            CalendarEntryType::Block => '#6b7280',             // gray
        };
    }
}
