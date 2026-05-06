<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\Health\UpcomingHealthAlertsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Three counters: overdue, due in 7 days, due in 30 days.
 * Click drills into HealthRecordResource with the right filter.
 */
class HealthAlertsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $counts = app(UpcomingHealthAlertsService::class)->counts();

        return [
            Stat::make('Przeterminowane', (string) $counts['overdue'])
                ->description('Zabiegi po terminie')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($counts['overdue'] > 0 ? 'danger' : 'gray')
                ->url(url('/app/health-records?tableFilters[overdue][isActive]=1')),

            Stat::make('W ciągu 7 dni', (string) $counts['due_within_7_days'])
                ->description('Najbliższe szczepienia / wizyty')
                ->descriptionIcon('heroicon-m-clock')
                ->color($counts['due_within_7_days'] > 0 ? 'warning' : 'gray'),

            Stat::make('W ciągu 30 dni', (string) $counts['due_within_30_days'])
                ->description('Łącznie najbliższych')
                ->descriptionIcon('heroicon-m-calendar')
                ->color($counts['due_within_30_days'] > 0 ? 'primary' : 'gray')
                ->url(url('/app/health-records?tableFilters[due_30][isActive]=1')),
        ];
    }
}
