<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Domain\Transport\Favorites\TransportFavoriteManager;
use App\Filament\App\Pages\TransportEntry;
use App\Models\Central\TransportLead;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;

/**
 * Mały promo card na dashboardzie stajni — surface'uje moduł transport
 * dla stable tenantów (canUseTransport === true) którzy jeszcze NIE
 * zamówili transportu w ostatnich 30 dniach.
 *
 * Dismissal: session-based, ale persisted też w `users.preferences` (jeśli
 * kolumna istnieje) — pragmatyczna decyzja, nie tworzymy nowej migracji
 * tylko żeby zhide'ować widget. Session-only oznacza re-pojawi się po
 * logout, ale i tak refresh raz na 30 dni gdy user złoży lead.
 *
 * Sort = -3 → pomiędzy TenantContextWidget(-10) a TodayStatsWidget(-5),
 * widoczny ale nie pierwszy.
 */
class TransportPromoWidget extends Widget
{
    protected static ?int $sort = -3;

    protected static string $view = 'filament.app.widgets.transport-promo';

    protected int|string|array $columnSpan = 'full';

    public bool $dismissed = false;

    public function mount(): void
    {
        $this->dismissed = (bool) session('transport_promo_dismissed', false);
    }

    public function dismiss(): void
    {
        session()->put('transport_promo_dismissed', true);
        $this->dismissed = true;
    }

    public static function canView(): bool
    {
        $tenant = app(TenantManager::class)->current();

        if ($tenant === null || ! $tenant->canUseTransport()) {
            return false;
        }

        // Session dismiss → ukryj. Tania check, bez DB hit.
        if ((bool) session('transport_promo_dismissed', false) === true) {
            return false;
        }

        // Don't spam — jeśli stable już zamówił transport w ostatnich 30
        // dniach, widget niepotrzebny. Sprawdzamy originator_tenant_id
        // bo tylko leady z poziomu /app mają tę kolumnę wypełnioną.
        $recentLeads = TransportLead::query()
            ->where('originator_tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        return ! $recentLeads;
    }

    public function getVerifiedTransportersCount(): int
    {
        return app(TransportFavoriteManager::class)
            ->availableTransportersQuery()
            ->count();
    }

    public function getEntryUrl(): string
    {
        return TransportEntry::getUrl();
    }
}
