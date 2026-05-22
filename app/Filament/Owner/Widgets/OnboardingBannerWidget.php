<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Pages\OnboardingWizard;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;

/**
 * Banner na dashboard'zie horse_owner: "Dokończ pierwsze kroki".
 * Patrz docstring App\OnboardingBannerWidget.
 */
class OnboardingBannerWidget extends Widget
{
    protected static ?int $sort = -8;

    protected static string $view = 'filament.owner.widgets.onboarding-banner';

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
