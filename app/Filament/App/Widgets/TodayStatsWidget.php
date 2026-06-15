<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Services\Dashboard\TodayDashboardService;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "Dziś" — four KPI tiles at the top of the /app dashboard.
 * Each tile links to the relevant resource pre-filtered.
 *
 * Each tile (except vacant_boxes, which is snapshot-only) carries a
 * 7-day sparkline + delta-vs-yesterday chip pulled from
 * `TodayDashboardService::trend()` — gives owner an at-a-glance sense
 * of direction without clicking through to a report.
 */
class TodayStatsWidget extends BaseWidget
{
    protected static ?int $sort = -5;

    protected function getStats(): array
    {
        $service = app(TodayDashboardService::class);
        $snapshot = $service->snapshot();
        $trend = $service->trend(7);

        $stats = [
            Stat::make(__('app/dashboard.today.bookings'), (string) $snapshot['bookings_today'])
                ->description($this->descriptionWithDelta(
                    base: __('app/dashboard.today.bookings_desc'),
                    series: $trend['bookings_today'],
                ))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($trend['bookings_today'])
                ->color($snapshot['bookings_today'] > 0 ? 'primary' : 'gray')
                ->url(url('/app/calendar')),

            Stat::make(__('app/dashboard.today.vacant_boxes'), (string) $snapshot['vacant_boxes'])
                ->description(__('app/dashboard.today.vacant_boxes_desc'))
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($snapshot['vacant_boxes'] > 0 ? 'success' : 'gray')
                ->url(url('/app/boxes')),

            Stat::make(__('app/dashboard.today.overdue_care'), (string) $snapshot['overdue_care'])
                ->description($this->descriptionWithDelta(
                    base: __('app/dashboard.today.overdue_care_desc'),
                    series: $trend['overdue_care'],
                    invertColors: true, // less overdue = better
                ))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->chart($trend['overdue_care'])
                ->color($snapshot['overdue_care'] > 0 ? 'danger' : 'gray')
                ->url(url('/app/health-records?tableFilters[overdue][isActive]=1')),
        ];

        // Unpaid invoices tile — tylko dla FINANCE_STAFF. Vet/instructor/
        // employee nie powinni widzieć kwot finansowych nawet jako KPI
        // na dashboard'zie (canAccess InvoiceResource i tak ich blokuje,
        // ale ten kafelek omijał gate). Po stronie role-blocked usera
        // tablica skraca się do 3 stat-ów — layout sam się przegrupuje.
        if (app(TenantRoleGate::class)->allows(TenantRoleGate::FINANCE_STAFF)) {
            $unpaidTotal = number_format($snapshot['unpaid_invoices_total_cents'] / 100, 2, ',', ' ').' zł';
            $countDescription = trans_choice(
                'app/dashboard.today.unpaid_invoices_desc',
                $snapshot['unpaid_invoices_count'],
                ['count' => $snapshot['unpaid_invoices_count']]
            );
            $stats[] = Stat::make(__('app/dashboard.today.unpaid_invoices'), $unpaidTotal)
                ->description($this->descriptionWithDelta(
                    base: $countDescription,
                    series: $trend['unpaid_invoices_total_cents'],
                    invertColors: true, // less unpaid = better
                    asCurrency: true,
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($trend['unpaid_invoices_total_cents'])
                ->color($snapshot['unpaid_invoices_count'] > 0 ? 'warning' : 'gray')
                ->url(url('/app/invoices?tableFilters[status][value]=issued'));
        }

        return $stats;
    }

    /**
     * Append " · ↑/↓X vs wczoraj" to a description when the trend has
     * a meaningful delta. `invertColors`: for metrics where smaller is
     * better (overdue care, unpaid invoices) — flip the arrow tint
     * semantically so green = improving, red = worsening.
     *
     * @param  list<int>  $series
     */
    private function descriptionWithDelta(
        string $base,
        array $series,
        bool $invertColors = false,
        bool $asCurrency = false,
    ): string {
        if (count($series) < 2) {
            return $base;
        }
        $today = (int) end($series);
        $yesterday = (int) $series[count($series) - 2];
        $delta = $today - $yesterday;

        if ($delta === 0) {
            return $base.' · '.__('app/dashboard.today.delta_flat');
        }

        $arrow = $delta > 0 ? '↑' : '↓';
        $abs = $asCurrency
            ? number_format(abs($delta) / 100, 0, ',', ' ').' zł'
            : (string) abs($delta);

        return $base.' · '.$arrow.$abs.' '.__('app/dashboard.today.delta_suffix');
    }
}
