<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Owner\Widgets\LastOwnerActivityWidget;
use App\Filament\Owner\Widgets\NotificationsStatsWidget;
use App\Filament\Owner\Widgets\UpcomingTransportWidget;
use App\Http\Middleware\EnforceImpersonationExpiry;
use App\Http\Middleware\InitialiseTenantFromSession;
use App\Http\Middleware\RedirectIfTenantSuspended;
use App\Http\Middleware\RequireTenantType;
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
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel dla `TenantType::HorseOwner` — konsumenckiej strony marketplace'u.
 *
 * Owner widzi:
 *   - Dashboard (status aktywnych zamówień transportu, ostatnie offers)
 *   - Moje konie (CRUD per-tenant)
 *   - Zamówienia transportu (placem leadów + responses)
 *   - Faktury (FV wystawione przez transporterów/stajnie)
 *   - Ustawienia (profil, notyfikacje)
 *
 * Owner NIE ma:
 *   - Resource'ów stajennych (boxes, pensjonariusze, vet, harmonogram)
 *   - Resource'ów transportowych (vehicles, drivers)
 *   - Billing (FREE tier — brak subscription do zarządzania)
 *
 * Brand identyczny z AppPanelProvider (stable panel) — ten sam ochre/cream
 * scheme. Owner ma się czuć "u siebie" w ekosystemie Hovera.
 */
class OwnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('owner')
            ->path('owner')
            ->brandName('hovera')
            ->brandLogo(asset('img/brand/hovera-logo.svg'))
            ->favicon(asset('favicon.svg'))
            ->login()
            ->passwordReset()
            ->colors([
                // Brand parity z AppPanelProvider — patrz tam długi komentarz
                // o ewolucji palety. Owner widzi to samo cieplejsze brown'owe
                // gray + ochre primary.
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
            // Filament discovery — directory'es będą wypełniane w PR 3-6.
            // Stub'y istnieją (Dashboard) żeby panel mounted nawet bez
            // własnych Resources.
            ->discoverResources(in: app_path('Filament/Owner/Resources'), for: 'App\\Filament\\Owner\\Resources')
            ->discoverPages(in: app_path('Filament/Owner/Pages'), for: 'App\\Filament\\Owner\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Owner/Widgets'), for: 'App\\Filament\\Owner\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                // Faza 6 PR 6.2 — ostatnie unread notifications (database) z
                // ownersi pipeline'u (PR 6.1): wiadomości, faktury, wizyty.
                // Sort -10 — pierwsze widget pod AccountWidget'em.
                LastOwnerActivityWidget::class,
                NotificationsStatsWidget::class,
                UpcomingTransportWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('navigation.group.owner_horses'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.owner_transport'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.owner_finance'))->collapsible(),
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
                    ->label(fn () => app()->getLocale() === 'pl' ? 'Master admin' : 'Master admin')
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
                fn () => Blade::render('<x-impersonation-banner />'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => Blade::render('<x-pwa-head /><x-google-analytics />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render('<x-pwa-register /><x-places-autocomplete-script />'),
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
                // Owner panel: tylko `horse_owner` tenant'y (lub master admin).
                // Stable/transporter user który po loginie wpadnie na /owner
                // przez przypadek → bouncowany na właściwy panel (logika w
                // RequireTenantType::handle — redirect po typeu).
                RequireTenantType::class.':horse_owner',
                // Owner = FREE tier (TenantType::HorseOwner::isFreeTier() === true).
                // NIE używamy `RedirectIfTrialExpired` — owner nie ma trial'a
                // i nie ma billing'u do wyegzekwowania.
                RedirectIfTenantSuspended::class,
            ]);
    }
}
