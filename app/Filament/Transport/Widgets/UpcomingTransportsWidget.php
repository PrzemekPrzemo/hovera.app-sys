<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use Filament\Widgets\Widget;

/**
 * Transporty dziś + jutro (zaakceptowane oferty z preferred_date w tym
 * przedziale). Dispatcher widzi co go czeka. Patrz docs/TRANSPORT.md (krok E).
 */
class UpcomingTransportsWidget extends Widget
{
    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.transport.widgets.upcoming-transports';

    /**
     * @return array{today: mixed, tomorrow: mixed}
     */
    protected function getViewData(): array
    {
        return app(TransportDashboardService::class)->upcomingTransports();
    }
}
