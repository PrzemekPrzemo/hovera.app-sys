<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Profile;
use App\Http\Middleware\EnforceImpersonationExpiry;
use App\Http\Middleware\InitialiseTenantFromSession;
use App\Http\Middleware\RedirectIfTenantSuspended;
use App\Http\Middleware\RedirectIfTrialExpired;
use App\Http\Middleware\RequireTenantType;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
                // Brand: Ochre #A8956B (primary akcent).
                // Gray = warm scale, piąta iteracja po user feedbackach (#168
                //   "dalej trochę za ciemne"):
                //   PR #160 Stone: 28,25,23 — za ciemne
                //   PR #165: 44,37,32 — za ciemne
                //   PR #166: 58,47,37 — za ciemne ("trochę")
                //   PR #168: 74,60,47 — dalej za ciemne
                //   teraz:  108,86,64 (~+45% jeszcze)
                //
                // 950 → #524231 (panel root)
                // 900 → #6c5640 (main bg — medium-dark warm brown)
                // 800 → #846c54 (sidebar)
                // 700 → #9b846d (borders)
                //
                // Dla porównania Tailwind Stone-600 = 87,83,78 — nasze 900 jest
                // już jaśniejsze niż Stone-600 ale ze zdecydowanie cieplejszym tonem.
                'primary' => Color::hex('#A8956B'),
                'gray' => [
                    50 => '247, 244, 239',   // brand cream
                    100 => '238, 232, 222',
                    200 => '224, 215, 200',
                    300 => '200, 184, 164',  // brand taupe
                    400 => '168, 149, 107',  // brand ochre
                    500 => '143, 133, 118',  // brand stone
                    600 => '139, 119, 102',
                    700 => '155, 132, 109',
                    800 => '132, 108, 84',
                    900 => '108, 86, 64',    // main dark bg — wyraźnie jaśniejszy niż #168 (74,60,47)
                    950 => '82, 66, 49',     // deepest — wyraźnie jaśniejszy niż #168 (52,41,31)
                ],
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
                NavigationGroup::make(fn () => __('navigation.group.tools'))->collapsed()->collapsible(),
                NavigationGroup::make(fn () => __('navigation.group.settings'))->collapsed()->collapsible(),
            ])
            // Sidebar entry dla Stripe billing — link bezpośrednio do Blade
            // flow w BillingController. NavigationItem (zamiast Filament Page)
            // bo Filament Page pod /app/billing kolidowałaby z BillingController
            // route i Filament cicho rezygnuje z rejestracji `filament.app.pages.billing`,
            // co sypie błędem w nawigacji.
            ->navigationItems([
                NavigationItem::make('billing')
                    ->label(fn () => __('billing.navigation.label'))
                    ->icon('heroicon-o-credit-card')
                    ->group(fn () => __('navigation.group.settings'))
                    ->sort(90)
                    ->url(fn () => route('billing.show'))
                    ->visible(fn () => app(TenantRoleGate::class)->allows(TenantRoleGate::FULL_ADMINS)),
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
                    ->label(fn () => app()->getLocale() === 'pl' ? 'Zmień stajnię' : 'Switch stable')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn () => route('tenant.switch')),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => Blade::render('<x-trial-banner /><x-demo-banner /><x-impersonation-banner /><x-master-ads />'),
            )
            // PWA: manifest + Apple meta + service worker.
            // HEAD_END / BODY_END używamy bo Filament nie ma własnego API
            // do dorzucenia tagów do <head>, więc render hook jest jedyną
            // czystą drogą bez nadpisywania całego layoutu.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => Blade::render('<x-pwa-head /><x-demo-light-mode /><x-google-analytics />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render('<x-pwa-register />'),
            )
            // Centrum pomocy "?" + zgłaszanie błędów. Renderowane na końcu
            // topbara, między global search a user-menu — by uniknąć kolizji
            // z natywnymi przyciskami Filamenta używamy TOPBAR_END.
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
                // Symetrycznie do TransportPanelProvider — transporter który
                // wylądował na /app (np. po loginie /app/login bez explicit
                // panel routingu) jest bouncowany na /transport. Master admin
                // bypassed przez RequireTenantType lines 42-45.
                RequireTenantType::class.':stable',
                // Forces /app/* → /app/billing once trial_ends_at is past
                // and no Stripe subscription is bound. Must come AFTER
                // InitialiseTenantFromSession so $tenant is hydrated.
                RedirectIfTrialExpired::class,
                // Suspended tenants get bounced to a single landing page
                // so the owner sees "konto zawieszone, skontaktuj się"
                // instead of half-rendered panels — ordering matters,
                // must run after tenant init for the same reason.
                RedirectIfTenantSuspended::class,
            ]);
    }
}
