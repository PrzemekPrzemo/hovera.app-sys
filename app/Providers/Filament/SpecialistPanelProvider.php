<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Specialist\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel dla external specjalistów (vet / farrier / dietetyk) — PR O5 Channel B.
 *
 * Specjalista to cross-tenant identity (`App\Models\Central\ExternalSpecialist`),
 * NIE tenant user ani master-admin. Loguje się osobnym guardem `specialist`
 * (patrz `config/auth.php`). Dostęp do panelu gated przez
 * `ExternalSpecialist::canAccessPanel()` — wymaga dokończonego setup'u.
 *
 * Flow:
 *   1. Stable zaprasza → ExternalSpecialist + magic link (PR #456-#457)
 *   2. Vet klika link, ustawia hasło (SetupController, PR #458)
 *   3. Vet loguje się na /specialist/login → /specialist (ten panel)
 *
 * Panel jest central-scoped (brak tenant middleware) — w odróżnieniu od
 * OwnerPanelProvider/AppPanelProvider, które inicjalizują tenant z sesji.
 * Inbox / threading (epic 1.5) dojdzie jako Resource w app/Filament/Specialist.
 *
 * Brand parity z OwnerPanelProvider — ten sam ochre/cream scheme.
 */
class SpecialistPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('specialist')
            ->path('specialist')
            ->authGuard('specialist')
            ->brandName('hovera · specjalista')
            ->brandLogo(asset('img/brand/hovera-logo.svg'))
            ->favicon(asset('favicon.svg'))
            ->login()
            ->colors([
                // Brand parity z OwnerPanelProvider — ochre primary + cieplejsze
                // brown'owe gray.
                'primary' => Color::hex('#A8956B'),
                'gray' => [
                    50 => '247, 244, 239',
                    100 => '238, 232, 222',
                    200 => '224, 215, 200',
                    300 => '200, 184, 164',
                    400 => '168, 149, 107',
                    500 => '143, 133, 118',
                    600 => '139, 119, 102',
                    700 => '155, 132, 109',
                    800 => '132, 108, 84',
                    900 => '108, 86, 64',
                    950 => '82, 66, 49',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Specialist/Resources'), for: 'App\\Filament\\Specialist\\Resources')
            ->discoverPages(in: app_path('Filament/Specialist/Pages'), for: 'App\\Filament\\Specialist\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Specialist/Widgets'), for: 'App\\Filament\\Specialist\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => __('common.language.pl'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.set', ['locale' => 'pl']))
                    ->visible(fn () => app()->getLocale() !== 'pl'),
                MenuItem::make()
                    ->label(fn () => __('common.language.en'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.set', ['locale' => 'en']))
                    ->visible(fn () => app()->getLocale() !== 'en'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => Blade::render('<x-pwa-head /><x-google-analytics />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render('<x-pwa-register />'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // Brak tenant middleware — specjalista jest central-scoped.
                // canAccessPanel() na modelu egzekwuje dokończony setup.
                Authenticate::class,
            ]);
    }
}
