<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\Master\MasterMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MasterStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    /** Live values are cheap enough; refresh every 60s for a busy dashboard. */
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $m = app(MasterMetrics::class);
        $counts = $m->tenantCountsByStatus();
        $churn = $m->churnRate(30);

        return [
            Stat::make('Aktywne stajnie', (string) ($counts['active'] + $counts['trialing']))
                ->description($counts['trialing'].' w trialu · '.$counts['past_due'].' past due')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make('MRR', $m->formatCents($m->mrrCents()))
                ->description('ARR: '.$m->formatCents($m->arrCents()))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Churn 30d', number_format($churn * 100, 1, ',', ' ').'%')
                ->description($counts['churned'].' churned łącznie')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($churn > 0.05 ? 'danger' : 'gray'),

            Stat::make('Suspended', (string) $counts['suspended'])
                ->description('Zawieszone konta')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color($counts['suspended'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
