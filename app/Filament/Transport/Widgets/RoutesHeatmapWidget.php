<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use Filament\Widgets\Widget;

/**
 * Top 10 par województw na podstawie leadów otrzymanych przez transportera
 * w ostatnich 90 dniach (central transport_lead_responses JOIN transport_leads).
 * Komplementarne do TopCorridorsWidget — tamten pokazuje miasta na poziomie
 * quote, ten geografię na poziomie województw (większe agregaty, lepsza
 * "mapa" gdzie firma faktycznie operuje).
 *
 * Implementacja: sorted list z poziomymi paskami (CSS) — wybrana zamiast
 * 16×16 grid heatmap ponieważ przy realistycznych wolumenach (kilkadziesiąt
 * leadów / kwartał) większość komórek byłaby pusta. Lista czyta się szybciej.
 *
 * Patrz docs/TRANSPORT.md (krok F — heatmap fallback do sorted list).
 */
class RoutesHeatmapWidget extends Widget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.transport.widgets.routes-heatmap';

    /**
     * @return array{pairs: array<int, array{from:string,to:string,count:int,share:float}>}
     */
    protected function getViewData(): array
    {
        return [
            'pairs' => app(TransportDashboardService::class)->topVoivodeshipPairs(10, 90),
        ];
    }
}
