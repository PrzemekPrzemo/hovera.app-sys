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

        // Pull all rows and aggregate in PHP. Using `strftime` works on
        // SQLite but not MySQL; `TIMESTAMPDIFF` flips the dependency the
        // other way. Per-tenant calendar volume in a single month is
        // small (< few thousand entries) so PHP-side grouping is cheap
        // and DB-portable.
        $entries = CalendarEntry::query()
            ->whereIn('status', [
                CalendarEntryStatus::Confirmed->value,
                CalendarEntryStatus::Completed->value,
            ])
            ->whereNotNull('horse_id')
            ->whereBetween('starts_at', [$range->start, $range->end])
            ->get(['horse_id', 'starts_at', 'ends_at']);

        $aggregated = $entries
            ->groupBy('horse_id')
            ->map(fn (Collection $group, $horseId) => [
                'horse_id' => (string) $horseId,
                'lesson_count' => $group->count(),
                'seconds' => (int) $group->sum(
                    fn ($e) => $e->starts_at->diffInSeconds($e->ends_at)
                ),
            ])
            ->sortByDesc('lesson_count')
            ->values();

        $horseIds = $aggregated->pluck('horse_id')->all();
        $horses = Horse::query()
            ->whereIn('id', $horseIds)
            ->pluck('name', 'id');

        $rows = $aggregated->map(fn (array $r) => [
            'horse_id' => $r['horse_id'],
            'horse_name' => $horses->get($r['horse_id'], '—'),
            'lesson_count' => $r['lesson_count'],
            'hours' => round($r['seconds'] / 3600, 1),
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
