<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Landing page panelu specjalisty (PR O5 Channel B — SpecialistPanelProvider).
 *
 * Na razie placeholder — pokazuje AccountWidget (zarejestrowany w
 * SpecialistPanelProvider). Inbox / threading (epic 1.5) doczepi tu kolejne
 * widgety i własny InboxResource.
 */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|string|array
    {
        return 1;
    }
}
