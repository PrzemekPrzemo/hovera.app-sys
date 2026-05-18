<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 4 KPI tiles na górze dashboard'u transportera. Patrz docs/TRANSPORT.md
 * (krok E z feedbacku produkcyjnego):
 *   - MRR bieżący miesiąc (zapłacone FV w tym miesiącu)
 *   - Należności (wystawione + przeterminowane FV)
 *   - Przeterminowane FV (liczba + suma)
 *   - Oferty czekające na akceptację
 */
class TransportKpiWidget extends BaseWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $kpi = app(TransportDashboardService::class)->kpi();

        $mrr = number_format($kpi['mrr_month_cents'] / 100, 0, ',', ' ').' zł';
        $receivables = number_format($kpi['receivables_cents'] / 100, 0, ',', ' ').' zł';
        $overdueCents = number_format($kpi['overdue_cents'] / 100, 0, ',', ' ').' zł';

        return [
            Stat::make(__('transport/dashboard.kpi.mrr_month'), $mrr)
                ->description(__('transport/dashboard.kpi.mrr_month_desc'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($kpi['mrr_month_cents'] > 0 ? 'success' : 'gray'),

            Stat::make(__('transport/dashboard.kpi.receivables'), $receivables)
                ->description(__('transport/dashboard.kpi.receivables_desc'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($kpi['receivables_cents'] > 0 ? 'warning' : 'gray')
                ->url(url('/transport/transport-invoices?tableFilters[status][value]=issued')),

            Stat::make(__('transport/dashboard.kpi.overdue'), (string) $kpi['overdue_count'])
                ->description(__('transport/dashboard.kpi.overdue_desc', ['sum' => $overdueCents]))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($kpi['overdue_count'] > 0 ? 'danger' : 'gray')
                ->url(url('/transport/transport-invoices?tableFilters[status][value]=overdue')),

            Stat::make(__('transport/dashboard.kpi.pending_quotes'), (string) $kpi['pending_quotes'])
                ->description(__('transport/dashboard.kpi.pending_quotes_desc'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color($kpi['pending_quotes'] > 0 ? 'info' : 'gray')
                ->url(url('/transport/quotes?tableFilters[status][value]=sent')),
        ];
    }
}
