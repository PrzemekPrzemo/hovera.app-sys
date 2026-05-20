<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Resources\TransportOrderResource;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\TransportOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Owner dashboard: szybki przegląd "co się stało odkąd ostatnio
 * zaglądałem". Trzy karty stat'ów — każda z drill-down URL do
 * TransportOrderResource z odpowiednim filtrem.
 *
 *   1. Nowe oferty — wszystkie odpowiedzi przewoźników na MOJE leady
 *      (cross-DB query: tenant.transport_orders → central.transport_lead_responses).
 *   2. Zaakceptowane — zamówienia z status='accepted' (ostatnie 14 dni).
 *   3. Nadchodzące — zamówienia z preferred_date w ciągu 3 dni od dziś.
 *
 * MVP: nie trzymamy "seen_at" per-notification, tylko aggregate counts.
 * Drilldown na pełną listę w TransportOrderResource.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md PR 11.
 */
class NotificationsStatsWidget extends BaseWidget
{
    protected static ?int $sort = -15;

    protected function getStats(): array
    {
        $orderIds = TransportOrder::query()->pluck('central_lead_id')->all();

        $newOffersCount = $orderIds === []
            ? 0
            : TransportLeadResponse::query()
                ->whereIn('lead_id', $orderIds)
                ->whereNotNull('responded_at')
                ->count();

        $acceptedCount = TransportOrder::query()
            ->where('status', 'accepted')
            ->where('updated_at', '>=', now()->subDays(14))
            ->count();

        $upcomingCount = TransportOrder::query()
            ->whereIn('status', ['open', 'quoted', 'accepted'])
            ->whereDate('preferred_date', '>=', now()->toDateString())
            ->whereDate('preferred_date', '<=', now()->addDays(3)->toDateString())
            ->count();

        $listUrl = TransportOrderResource::getUrl('index');

        return [
            Stat::make(__('owner/transport.notifications.new_offers'), (string) $newOffersCount)
                ->description(__('owner/transport.notifications.new_offers_description'))
                ->descriptionIcon('heroicon-m-envelope')
                ->color($newOffersCount > 0 ? 'success' : 'gray')
                ->url($newOffersCount > 0 ? $listUrl : null),

            Stat::make(__('owner/transport.notifications.accepted'), (string) $acceptedCount)
                ->description(__('owner/transport.notifications.accepted_description'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($acceptedCount > 0 ? 'primary' : 'gray')
                ->url($acceptedCount > 0 ? $listUrl.'?tableFilters[status][value]=accepted' : null),

            Stat::make(__('owner/transport.notifications.upcoming'), (string) $upcomingCount)
                ->description(__('owner/transport.notifications.upcoming_description'))
                ->descriptionIcon('heroicon-m-truck')
                ->color($upcomingCount > 0 ? 'warning' : 'gray')
                ->url($upcomingCount > 0 ? $listUrl : null),
        ];
    }
}
