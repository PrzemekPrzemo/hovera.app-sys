<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 2 KPI tiles uzupełniające TransportKpiWidget — informacje o lead-flow
 * (central DB, transport_lead_responses):
 *   - Leady tygodniowo: ile zapytań trafiło do transportera w ostatnich 7d
 *     z deltą vs poprzedni tydzień.
 *   - Win rate 30d: accepted / total spośród naszych odpowiedzi z 30d, w punktach
 *     procentowych vs poprzedni okres.
 *
 * Patrz docs/TRANSPORT.md (krok F — mini-dashboard lead flow).
 */
class LeadsKpiWidget extends BaseWidget
{
    protected static ?int $sort = -9;

    protected function getStats(): array
    {
        $kpi = app(TransportDashboardService::class)->leadFlowKpi();

        return [
            $this->leadsStat($kpi),
            $this->winRateStat($kpi),
        ];
    }

    private function leadsStat(array $kpi): Stat
    {
        $delta = $kpi['leads_week_delta'];
        [$desc, $icon, $color] = $this->formatDelta(
            $delta,
            __('transport/dashboard.leads_kpi.leads_week_desc'),
            unit: '%',
        );

        return Stat::make(__('transport/dashboard.leads_kpi.leads_week'), (string) $kpi['leads_week'])
            ->description($desc)
            ->descriptionIcon($icon)
            ->color($color);
    }

    private function winRateStat(array $kpi): Stat
    {
        $rate = $kpi['win_rate_30d'];
        $value = $rate === null ? '—' : number_format($rate, 1, ',', '').'%';

        if ($rate === null) {
            return Stat::make(__('transport/dashboard.leads_kpi.win_rate'), $value)
                ->description(__('transport/dashboard.leads_kpi.win_rate_no_data'))
                ->descriptionIcon('heroicon-m-minus')
                ->color('gray');
        }

        $delta = $kpi['win_rate_30d_delta'];
        [$desc, $icon, $color] = $this->formatDelta(
            $delta,
            __('transport/dashboard.leads_kpi.win_rate_desc'),
            unit: 'pp',
        );

        return Stat::make(__('transport/dashboard.leads_kpi.win_rate'), $value)
            ->description($desc)
            ->descriptionIcon($icon)
            ->color($color);
    }

    /**
     * @return array{0:string, 1:string, 2:string}  [description, icon, color]
     */
    private function formatDelta(?float $delta, string $fallbackDesc, string $unit): array
    {
        if ($delta === null) {
            return [$fallbackDesc, 'heroicon-m-minus', 'gray'];
        }

        if ($delta > 0) {
            $sign = '+';
            $icon = 'heroicon-m-arrow-trending-up';
            $color = 'success';
        } elseif ($delta < 0) {
            $sign = '';
            $icon = 'heroicon-m-arrow-trending-down';
            $color = 'danger';
        } else {
            $sign = '';
            $icon = 'heroicon-m-minus';
            $color = 'gray';
        }

        $formatted = $sign.number_format($delta, 1, ',', '').$unit;

        return [
            __('transport/dashboard.leads_kpi.vs_prev', ['delta' => $formatted]),
            $icon,
            $color,
        ];
    }
}
