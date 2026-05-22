<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Filament\Transport\Pages\OnboardingWizard;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;

/**
 * Banner na dashboard'zie transporter: "Dokończ pierwsze kroki".
 * Widoczny gdy user widział wizarda (deferred_at) ale nie kliknął
 * Finish/Skip. Patrz docstring App\OnboardingBannerWidget.
 */
class OnboardingBannerWidget extends Widget
{
    protected static ?int $sort = -8;

    protected static string $view = 'filament.transport.widgets.onboarding-banner';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return false;
        }

        return $tenant->wasOnboardingShown() && ! $tenant->isOnboardingFinished();
    }

    public function getWizardUrl(): string
    {
        return OnboardingWizard::getUrl();
    }
}
