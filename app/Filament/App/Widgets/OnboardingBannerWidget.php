<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\OnboardingWizard;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;

/**
 * Banner na dashboard'zie stable: "Twój onboarding jest niedokończony,
 * dokończ tutaj →". Widoczny gdy stan settings.onboarding = deferred
 * (user widział wizarda ale nie kliknął Finish ani Skip). Znika po
 * explicit completed/skipped.
 *
 * Sort = -8 → na samej górze, nad statystykami (TodayStatsWidget = -5).
 *
 * Patrz docs/ROLE-MATRIX.md + Tenant::wasOnboardingShown/isOnboardingFinished.
 */
class OnboardingBannerWidget extends Widget
{
    protected static ?int $sort = -8;

    protected static string $view = 'filament.app.widgets.onboarding-banner';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return false;
        }

        // Pokazujemy gdy user widział wizarda (deferred) ale jeszcze nie
        // kliknął Finish / Skip. Dla nowych tenantów (wasOnboardingShown=false)
        // middleware redirectuje na wizarda; po pierwszym mount'cie wizard
        // ustawi deferred_at → kolejne wejścia widzą tę kartę.
        return $tenant->wasOnboardingShown() && ! $tenant->isOnboardingFinished();
    }

    public function getWizardUrl(): string
    {
        return OnboardingWizard::getUrl();
    }
}
