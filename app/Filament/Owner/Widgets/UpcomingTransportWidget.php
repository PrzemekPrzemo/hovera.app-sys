<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Models\Tenant\TransportOrder;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Owner dashboard: nadchodzące zamówienia transportu (status open/quoted/
 * accepted, preferred_date >= dzisiaj). Empty state z CTA do
 * `/owner/order-transport`.
 */
class UpcomingTransportWidget extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.owner.widgets.upcoming-transport';

    /**
     * @return array{orders: Collection<int,TransportOrder>}
     */
    protected function getViewData(): array
    {
        $orders = TransportOrder::query()
            ->whereIn('status', ['open', 'quoted', 'accepted'])
            ->whereDate('preferred_date', '>=', now()->toDateString())
            ->orderBy('preferred_date')
            ->limit(5)
            ->get();

        return ['orders' => $orders];
    }
}
