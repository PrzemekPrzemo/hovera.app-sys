<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Profile;
use App\Http\Middleware\EnsureMasterAdmin;
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
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path(config('hovera.admin.path', 'admin'))
            ->brandName('hovera · master')
            ->brandLogo(asset('img/brand/hovera-logo.svg'))
            ->favicon(asset('favicon.svg'))
            // Świadomie BRAK ->login() — master admin loguje się przez wspólny
            // /app/login (route 'login'). Filament Authenticate middleware
            // redirectuje na route('login') gdy brak panelu auth. Po zalogowaniu
            // canAccessPanel('admin') sprawdza is_master_admin = true.
            // Dlaczego: właściciele stajni częściej trafiają na /admin/login
            // przez pomyłkę — fallback redirect w routes/web.php prowadzi ich
            // na właściwy /app/login.
            ->passwordReset()
            ->colors([
                // Brand: Ochre #A8956B (primary akcent), Deep Brown #3D2E22 (gray sidebar)
                'primary' => Color::hex('#A8956B'),
                'gray' => Color::hex('#3D2E22'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Pages\Dashboard::class,
                Profile::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            // Sidebar groups w master adminie. Filament wymaga ikon
            // albo na grupie albo na itemach — wybieramy ikony per-resource.
            ->navigationGroups([
                NavigationGroup::make(fn () => __('navigation.group.stables'))->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.configuration'))->collapsed()->collapsible(),
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
            ])
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
                EnsureMasterAdmin::class,
            ]);
    }
}
