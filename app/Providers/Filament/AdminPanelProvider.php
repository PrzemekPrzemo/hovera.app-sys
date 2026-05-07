<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Profile;
use App\Http\Middleware\EnsureMasterAdmin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            ->default()
            ->id('admin')
            ->path(config('hovera.admin.path', 'admin'))
            ->brandName('hovera · master')
            ->brandLogo(asset('img/brand/hovera-logo.svg'))
            ->favicon(asset('favicon.svg'))
            ->login()
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
                NavigationGroup::make('Stajnie')->collapsible(),
                NavigationGroup::make('Konfiguracja')->collapsed()->collapsible(),
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
