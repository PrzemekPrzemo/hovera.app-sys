<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Profile;
use App\Http\Middleware\EnforceImpersonationExpiry;
use App\Http\Middleware\InitialiseTenantFromSession;
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

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->brandName('hovera')
            ->brandLogo(asset('img/brand/hovera-logo.svg'))
            ->favicon(asset('favicon.svg'))
            ->login()
            ->passwordReset()
            ->colors([
                // Brand: Ochre #A8956B (primary akcent), Deep Brown #3D2E22 (gray sidebar)
                'primary' => Color::hex('#A8956B'),
                'gray' => Color::hex('#3D2E22'),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                Pages\Dashboard::class,
                Profile::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            // Sidebar layout — explicit groups z collapsible state.
            // Filament: ikony albo na grupach albo na itemach, nie obu —
            // zostawiamy ikony per-resource (lepsza czytelność niż wspólna
            // ikona grupy). Kolejność tutaj wyznacza kolejność na sidebarze.
            ->navigationGroups([
                NavigationGroup::make(fn () => __('navigation.group.stable'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.calendar'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.finances'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.reports'))->collapsible(),
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
                    ->label(fn () => app()->getLocale() === 'pl' ? 'Master admin' : 'Master admin')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn () => '/'.config('hovera.admin.path', 'admin'))
                    ->visible(fn () => Auth::user()?->is_master_admin === true),
                MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'pl' ? 'Zmień stajnię' : 'Switch stable')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn () => route('tenant.switch')),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => Blade::render('<x-demo-banner /><x-impersonation-banner />'),
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
            ]);
    }
}
