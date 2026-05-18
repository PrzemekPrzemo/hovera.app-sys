<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use Filament\Widgets\Widget;

/**
 * Top 10 korytarzy (pickup → dropoff). Pomaga zobaczyć gdzie firma robi
 * największy biznes ("60% to Trójmiasto-Warszawa"). Patrz docs/TRANSPORT.md
 * (krok E).
 *
 * Renderowane jako prosta tabela + horizontal bars (CSS) zamiast prawdziwej
 * heatmapy — wystarczające dla MVP, dorobimy mapę gdy będzie wymagana.
 */
class TopCorridorsWidget extends Widget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.transport.widgets.top-corridors';

    /**
     * @return array<int, array{from:string,to:string,count:int,share:float}>
     */
    protected function getViewData(): array
    {
        return [
            'corridors' => app(TransportDashboardService::class)->topCorridors(10),
        ];
    }
}
