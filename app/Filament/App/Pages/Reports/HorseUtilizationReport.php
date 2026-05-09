<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Enums\CalendarEntryStatus;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Horse;
use App\Services\Reports\MonthRange;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Horse utilization — count of completed/confirmed lessons per horse
 * in the picked month. High counts (>20/mo) flag risk of overwork.
 */
class HorseUtilizationReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 93;

    protected static string $view = 'filament.app.pages.reports.horse-utilization';

    #[Url]
    public ?string $month = null;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.reports.horse_utilization.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.reports.horse_utilization.title');
    }

    public function range(): MonthRange
    {
        return MonthRange::from($this->month);
    }

    /**
     * @return array{
     *     range:MonthRange,
     *     rows: Collection,
     * }
     */
    public function snapshot(): array
    {
        $range = $this->range();

        // Two-step: count entries grouped by horse_id, then enrich with name.
        $counts = CalendarEntry::query()
            ->selectRaw('horse_id, COUNT(*) as lesson_count, SUM(strftime(\'%s\', ends_at) - strftime(\'%s\', starts_at)) as seconds')
            ->whereIn('status', [
                CalendarEntryStatus::Confirmed->value,
                CalendarEntryStatus::Completed->value,
            ])
            ->whereNotNull('horse_id')
            ->whereBetween('starts_at', [$range->start, $range->end])
            ->groupBy('horse_id')
            ->orderByDesc('lesson_count')
            ->get();

        // Use a generic time difference fallback for MySQL, then PHP-side
        // recompute hours from `lesson_count * avg_minutes` if the seconds
        // column is null (cross-DB compat).
        if ($counts->isNotEmpty() && $counts->first()->seconds === null) {
            $counts = $counts->map(function ($r) use ($range) {
                $totalSeconds = (int) CalendarEntry::query()
                    ->whereBetween('starts_at', [$range->start, $range->end])
                    ->where('horse_id', $r->horse_id)
                    ->whereIn('status', [
                        CalendarEntryStatus::Confirmed->value,
                        CalendarEntryStatus::Completed->value,
                    ])
                    ->get()
                    ->sum(fn ($e) => $e->starts_at->diffInSeconds($e->ends_at));
                $r->seconds = $totalSeconds;

                return $r;
            });
        }

        $horseIds = $counts->pluck('horse_id')->all();
        $horses = Horse::query()
            ->whereIn('id', $horseIds)
            ->pluck('name', 'id');

        $rows = $counts->map(fn ($r) => [
            'horse_id' => (string) $r->horse_id,
            'horse_name' => $horses->get($r->horse_id, '—'),
            'lesson_count' => (int) $r->lesson_count,
            'hours' => round(((int) $r->seconds) / 3600, 1),
        ]);

        return [
            'range' => $range,
            'rows' => $rows,
        ];
    }

    public function colorForCount(int $count): string
    {
        return match (true) {
            $count >= 25 => 'danger',
            $count >= 15 => 'warning',
            $count >= 1 => 'success',
            default => 'gray',
        };
    }
}
