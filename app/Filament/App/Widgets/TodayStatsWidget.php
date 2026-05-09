<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\Dashboard\TodayDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "Dziś" — four KPI tiles at the top of the /app dashboard.
 * Each tile links to the relevant resource pre-filtered.
 */
class TodayStatsWidget extends BaseWidget
{
    protected static ?int $sort = -5;

    protected function getStats(): array
    {
        $snapshot = app(TodayDashboardService::class)->snapshot();

        $unpaidTotal = number_format($snapshot['unpaid_invoices_total_cents'] / 100, 2, ',', ' ').' zł';

        return [
            Stat::make(__('app/dashboard.today.bookings'), (string) $snapshot['bookings_today'])
                ->description(__('app/dashboard.today.bookings_desc'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($snapshot['bookings_today'] > 0 ? 'primary' : 'gray')
                ->url(url('/app/calendar')),

            Stat::make(__('app/dashboard.today.vacant_boxes'), (string) $snapshot['vacant_boxes'])
                ->description(__('app/dashboard.today.vacant_boxes_desc'))
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($snapshot['vacant_boxes'] > 0 ? 'success' : 'gray')
                ->url(url('/app/boxes')),

            Stat::make(__('app/dashboard.today.overdue_care'), (string) $snapshot['overdue_care'])
                ->description(__('app/dashboard.today.overdue_care_desc'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($snapshot['overdue_care'] > 0 ? 'danger' : 'gray')
                ->url(url('/app/health-records?tableFilters[overdue][isActive]=1')),

            Stat::make(__('app/dashboard.today.unpaid_invoices'), $unpaidTotal)
                ->description(trans_choice(
                    'app/dashboard.today.unpaid_invoices_desc',
                    $snapshot['unpaid_invoices_count'],
                    ['count' => $snapshot['unpaid_invoices_count']]
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($snapshot['unpaid_invoices_count'] > 0 ? 'warning' : 'gray')
                ->url(url('/app/invoices?tableFilters[status][value]=issued')),
        ];
    }
}
