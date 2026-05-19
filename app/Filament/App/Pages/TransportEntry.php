<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Transport\Favorites\TransportFavoriteManager;
use App\Models\Central\TransportLead;
use App\Tenancy\TenantManager;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\RedirectResponse;

/**
 * Stable-side entry point dla modułu transportu (PR #229 + ten PR).
 *
 * Marketing spec: stables dostają moduł transport BEZPŁATNIE w ramach planu
 * Hovery (Start+). Ta strona pełni rolę dyskoverabilnego entry-pointu z 3
 * ścieżkami discovery do zamówienia transportu:
 *
 *   1. Broadcast    — /transport/zapytanie?from=app&stable=…
 *   2. Directory    — /przewoznicy (publiczny katalog firm)
 *   3. Ulubieni     — /app/transport-favorites (zarządzanie listą max 5)
 *
 * Patrz docs/TRANSPORT.md §1.3 (pozycjonowanie) + §5 (routing leadu).
 *
 * Gating:
 *   • canAccess()         → canUseTransport() === true (free plan → 403/redirect)
 *   • shouldRegisterNavigation() → to samo (nav item ukryty dla free)
 *   • mount() z bezpośrednim hitem URL na free → redirect na billing
 *     (gracieusniejszy UX niż 403; spójny z RedirectIfTrialExpired wzorcem).
 */
class TransportEntry extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.app.pages.transport-entry';

    protected static ?string $slug = 'transport';

    public static function getNavigationLabel(): string
    {
        return __('app/transport_entry.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.tools');
    }

    public function getTitle(): string|Htmlable
    {
        return __('app/transport_entry.title');
    }

    /**
     * Stable owner / manager (i każdy z dostępem do panelu) widzi entry,
     * ale tylko gdy plan pozwala (canUseTransport). Free plan → ukryte.
     */
    public static function canAccess(): bool
    {
        $tenant = app(TenantManager::class)->current();

        if ($tenant === null) {
            return false;
        }

        return $tenant->canUseTransport();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    /**
     * Direct URL hit on `free` plan → bounce na billing zamiast 403.
     * Lepsze UX bo właściciel widzi "upgrade →" zamiast tylko error code.
     */
    public function mount(): ?RedirectResponse
    {
        $tenant = app(TenantManager::class)->current();

        if ($tenant === null) {
            return null;
        }

        if (! $tenant->canUseTransport()) {
            session()->flash('transport_upgrade_required', __('app/transport_entry.upgrade_required'));

            return redirect()->route('billing.show');
        }

        return null;
    }

    /**
     * Liczba transport leadów zainicjowanych przez tę stajnię. Inkrementowane
     * przez TransportInquiryController gdy `?stable={id}` jest podany i user
     * jest authorized member tego tenanta.
     */
    public function getStableLeadsCount(): int
    {
        $tenant = app(TenantManager::class)->current();

        if ($tenant === null) {
            return 0;
        }

        return TransportLead::query()
            ->where('originator_tenant_id', $tenant->id)
            ->count();
    }

    public function getFavoritesCount(): int
    {
        $tenant = app(TenantManager::class)->current();

        if ($tenant === null) {
            return 0;
        }

        return count(app(TransportFavoriteManager::class)->idsFor($tenant));
    }

    /**
     * Liczba zweryfikowanych transporterów dostępnych w katalogu — używana
     * jako social-proof na hero ("łączymy Cię z X firmami"). Cache 5min
     * w warstwie zapytania niepotrzebny — to count na małej central tabeli.
     */
    public function getVerifiedTransportersCount(): int
    {
        return app(TransportFavoriteManager::class)
            ->availableTransportersQuery()
            ->count();
    }

    public function getBroadcastUrl(): string
    {
        $tenant = app(TenantManager::class)->current();

        return route('public.transport.inquiry').'?from=app'
            .($tenant !== null ? '&stable='.$tenant->id : '');
    }

    public function getDirectoryUrl(): string
    {
        // Publiczny katalog (z parallel agent). Nie znamy nazwy route'a,
        // hardcode'ujemy ścieżkę bo to public URL pod tym samym hostem.
        return '/przewoznicy';
    }

    public function getFavoritesUrl(): string
    {
        return TransportFavorites::getUrl();
    }
}
