<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Profile;
use App\Filament\Transport\Pages\Calculator;
use App\Filament\Transport\Pages\TransportDashboard;
use App\Filament\Transport\Widgets\LeadsKpiWidget;
use App\Filament\Transport\Widgets\PendingInvoicesWidget;
use App\Filament\Transport\Widgets\RoutesHeatmapWidget;
use App\Filament\Transport\Widgets\TopCorridorsWidget;
use App\Filament\Transport\Widgets\TopPaidInvoicesWidget;
use App\Filament\Transport\Widgets\TransportKpiWidget;
use App\Filament\Transport\Widgets\UpcomingTransportsWeekWidget;
use App\Filament\Transport\Widgets\UpcomingTransportsWidget;
use App\Http\Middleware\EnforceImpersonationExpiry;
use App\Http\Middleware\InitialiseTenantFromSession;
use App\Http\Middleware\RedirectIfTenantSuspended;
use App\Http\Middleware\RedirectIfTrialExpired;
use App\Http\Middleware\RedirectToOnboarding;
use App\Http\Middleware\RequireTenantType;
use App\Tenancy\TenantManager;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel dla tenant'ów typu transporter — patrz docs/TRANSPORT.md §3.1.
 *
 * Struktura analogiczna do AppPanelProvider, ale:
 *   - id/path = 'transport' zamiast 'app'
 *   - własny zestaw NavigationGroup'ów (Fleet, Dispatch, Finances, Settings)
 *   - RequireTenantType:transporter bramuje dostęp tylko dla typu transporter
 *     (stable tenants są przerzucani na /app)
 *
 * W kroku 2 panel jest skeletonem — resource'y (Vehicle, Lead, Quote) wpadają
 * w kolejnych krokach. Discover* są już ustawione, więc kolejne PR-y dodają
 * pliki i są od razu widoczne w sidebarze.
 */
class TransportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('transport')
            ->path('transport')
            // Explicit home URL — bez tego po loginie Filament redirectuje na
            // panel root `/transport` (default homeUrl), gdzie publiczny
            // `TransportLandingController` przejmuje (routes/web.php) i pokazuje
            // SEO landing zamiast panelu. TransportDashboard ma `routePath='/dashboard'`,
            // więc homeUrl musi być explicit. Patrz docs/TRANSPORT.md §3.1.
            ->homeUrl('/transport/dashboard')
            ->brandName(function () {
                // Tenant name w topbar — fallback do "hovera · transport"
                // gdy tenant nieustawiony (np. /transport/login przed auth).
                $name = trim((string) (app(TenantManager::class)->current()?->name ?? ''));

                return $name !== '' ? $name : 'hovera · transport';
            })
            ->brandLogo(asset('img/brand/hovera-logo.svg'))
            ->favicon(asset('favicon.svg'))
            ->login()
            ->passwordReset()
            ->colors([
                // Identyczna paleta jak w AppPanelProvider — to ten sam brand.
                // Synchronizacja paletą wymaga ręcznej edycji obu plików; jeśli
                // brand się zmieni, patrz AppPanelProvider:colors() jako źródło prawdy.
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
            ->discoverResources(in: app_path('Filament/Transport/Resources'), for: 'App\\Filament\\Transport\\Resources')
            ->discoverPages(in: app_path('Filament/Transport/Pages'), for: 'App\\Filament\\Transport\\Pages')
            ->pages([
                // Explicit registration — discoverPages teoretycznie wystarcza,
                // ale wybrane pages chcemy mieć z pewnym slotem w sidebarze
                // (Dashboard jako home + Calculator jako kluczowy CTA dla
                // transportera) niezależnie od kolejności auto-discovery.
                //
                // TransportDashboard (custom) zastępuje domyślny
                // Pages\Dashboard — pokazuje hero CTA grid + onboarding
                // checklist + wszystkie zarejestrowane widgety poniżej.
                TransportDashboard::class,
                Calculator::class,
                Profile::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Transport/Widgets'), for: 'App\\Filament\\Transport\\Widgets')
            ->widgets([
                TransportKpiWidget::class,
                LeadsKpiWidget::class,
                UpcomingTransportsWidget::class,
                UpcomingTransportsWeekWidget::class,
                PendingInvoicesWidget::class,
                TopPaidInvoicesWidget::class,
                TopCorridorsWidget::class,
                RoutesHeatmapWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('navigation.group.fleet'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.dispatch'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.finances'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.settings'))->collapsed()->collapsible(),
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
                MenuItem::make()
                    ->label(fn () => __('common.language.fr'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.set', ['locale' => 'fr']))
                    ->visible(fn () => app()->getLocale() !== 'fr'),
                MenuItem::make()
                    ->label(fn () => __('common.language.de'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.set', ['locale' => 'de']))
                    ->visible(fn () => app()->getLocale() !== 'de'),
                MenuItem::make()
                    ->label(fn () => __('common.language.ru'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.set', ['locale' => 'ru']))
                    ->visible(fn () => app()->getLocale() !== 'ru'),
                MenuItem::make()
                    ->label(fn () => 'Master admin')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn () => '/'.config('hovera.admin.path', 'admin'))
                    ->visible(fn () => Auth::user()?->is_master_admin === true),
                MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'pl' ? 'Zmień konto' : 'Switch account')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn () => route('tenant.switch')),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => Blade::render('<x-trial-banner /><x-impersonation-banner /><x-transport-verification-banner />'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => Blade::render('<x-pwa-head /><x-google-analytics />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render('<x-pwa-register /><x-places-autocomplete-script />'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => Blade::render('<x-help-and-bug-topbar />'),
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
                Authenticate::class,
                EnforceImpersonationExpiry::class,
                InitialiseTenantFromSession::class,
                RedirectIfTrialExpired::class,
                RedirectIfTenantSuspended::class,
                RequireTenantType::class.':transporter',
                RedirectToOnboarding::class,
            ]);
    }
}
