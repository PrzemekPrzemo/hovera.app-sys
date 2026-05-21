<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Domain\Horses\Snapshots\HorseSnapshot;
use App\Domain\Horses\StableHorseSnapshotService;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Owner: szczegóły konia goszczącego w stajni. Pierwsza strona z Fazy 1
 * Owner ↔ Stable shared view (patrz docs/OWNER-STABLE-ROADMAP.md).
 *
 * URL: /owner/horses/{centralHorseId}/details
 *
 * Mount flow:
 *   1. Resolve auth user (Filament authMiddleware już to robi)
 *   2. HorseOwnerStableAccessGate::authorize() — sprawdza primary_owner
 *      + active boarding. Brak = 403.
 *   3. StableHorseSnapshotService::forCentralHorse() — odpala
 *      TenantManager::execute z stable tenant, czyta Horse + relacje,
 *      zwraca HorseSnapshot DTO.
 *   4. Render Blade view'u z DTO w property.
 *
 * Faza 1 = read-only view podstawowych danych + boksu + boarding services.
 * Faza 2 (timeline), 3 (invoices), 4 (messages), 5 (files) doszyją się
 * jako kolejne taby na tym samym slug'u.
 */
class HorseDetail extends Page
{
    protected static ?string $slug = 'horses/{centralHorseId}/details';

    protected static string $view = 'filament.owner.pages.horse-detail';

    /**
     * Strona NIE pojawia się w navigation — wchodzimy z listy
     * `/owner/horses` (przycisk "Szczegóły boardingu" jak będzie active
     * assignment). Faza 1: link można wkleić ręcznie, UI list-side
     * doszyte w Faza 2 timeline PR.
     */
    protected static bool $shouldRegisterNavigation = false;

    public string $centralHorseId = '';

    public ?HorseSnapshot $snapshot = null;

    public ?Tenant $stableTenant = null;

    public ?HorseBoardingAssignment $assignment = null;

    public function getTitle(): string|Htmlable
    {
        return $this->snapshot?->name ?? __('owner/horse_detail.title.fallback');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/owner/horses') => __('owner/horses.navigation'),
            __('owner/horse_detail.breadcrumb') => '',
        ];
    }

    public function mount(string $centralHorseId): void
    {
        $this->centralHorseId = $centralHorseId;
        $user = Auth::user();
        abort_unless($user !== null, 401);

        try {
            $this->assignment = app(HorseOwnerStableAccessGate::class)
                ->authorize($user, $centralHorseId);
        } catch (AuthorizationException) {
            abort(403, __('owner/horse_detail.access.denied'));
        }

        $this->stableTenant = Tenant::query()->find($this->assignment->stable_tenant_id);
        abort_unless($this->stableTenant !== null, 404);

        try {
            $this->snapshot = app(StableHorseSnapshotService::class)
                ->forCentralHorse($centralHorseId, $this->stableTenant);
        } catch (RuntimeException $e) {
            // Sync rift między central registry a stable DB — log + 404
            // żeby user widział że coś jest nie tak (zamiast 500). Stable
            // operator powinien sprawdzić czy nie usunął rekordu accidentally.
            report($e);
            abort(404, __('owner/horse_detail.access.sync_rift'));
        }
    }

    /**
     * Helper dla view'u — formatuje cents → "1 234,56 zł" (PL formatting,
     * currency configurowalna w przyszłości).
     */
    public function formatCents(?int $cents, string $currency = 'PLN'): string
    {
        if ($cents === null) {
            return '—';
        }

        return number_format($cents / 100, 2, ',', ' ').' '.$currency;
    }
}
