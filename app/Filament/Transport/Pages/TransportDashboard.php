<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\Dashboard\TransportHomeStatsService;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Custom landing dashboard dla `/transport` — zastępuje domyślny
 * `Filament\Pages\Dashboard` żeby pokazać hero CTA grid (Calculator,
 * Inbox, Quotes, Invoices) + onboarding checklist na górze, a obecne
 * widgety KPI/finansowe poniżej.
 *
 * Liczniki w kartach (np. „3 nowe zapytania") są per-tenant — wyciągane
 * przez `TransportHomeStatsService`, świeżo z DB przy każdym render
 * (cache niepotrzebny — count() na indeksowanych kolumnach).
 *
 * Widget'y zostają obsłużone przez bazową implementację `Dashboard::class`
 * z `TransportPanelProvider` — `getWidgets()` zwraca listę zarejestrowanych
 * tam widgetów. Nasz custom view ($view) sam je renderuje.
 */
class TransportDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.transport.pages.transport-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('transport/dashboard.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/dashboard.title');
    }

    /**
     * Dane dla view'a — counts + checklist. Filament prebinduje publiczne
     * property'sy do Livewire stejtu; trzymamy je tu zamiast wewnątrz
     * view'a żeby checklist nie regenerował się przy każdym widget
     * re-render.
     *
     * @return array{
     *   unseen_leads:int,
     *   pending_quotes:int,
     *   unpaid_invoices:int,
     *   unpaid_invoices_cents:int,
     * }
     */
    public function getHeroCounts(): array
    {
        return app(TransportHomeStatsService::class)->heroCounts();
    }

    /**
     * @return array{
     *   verified:bool,
     *   has_vehicles:bool,
     *   has_drivers:bool,
     *   has_service_areas:bool,
     *   completed_count:int,
     *   total_count:int,
     * }
     */
    public function getOnboardingChecklist(): array
    {
        return app(TransportHomeStatsService::class)->onboardingChecklist();
    }
}
