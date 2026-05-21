<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Domain\Horses\OwnerHorseCareService;
use App\Domain\Horses\Snapshots\HorseFeedingPlanItemSnapshot;
use App\Domain\Horses\Snapshots\HorseWeightSnapshot;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Owner panel: waga konia (timeline pomiarów + trend) + plan żywienia.
 * Read-only — owner widzi co stajnia loguje, ale nie edytuje (CRUD po
 * stronie stajni w HorseResource RelationManagerach).
 *
 * URL: /owner/horses/{centralHorseId}/care
 *
 * Faza 1 follow-up (post-roadmap) — domyka punkty A.5 (pomiary masy)
 * + A.6 (plan żywienia) z OWNER-STABLE-ROADMAP.md.
 *
 * Q3 — ended boarding pozwala read-only (zgodnie z innymi owner page'ami
 * jak Gallery/Documents). Active + ended dają dostęp; pending/disputed
 * blokuje.
 */
class HorseCare extends Page
{
    protected static ?string $slug = 'horses/{centralHorseId}/care';

    protected static string $view = 'filament.owner.pages.horse-care';

    protected static bool $shouldRegisterNavigation = false;

    public string $centralHorseId = '';

    public ?Tenant $stableTenant = null;

    public ?HorseBoardingAssignment $assignment = null;

    /** @var list<HorseWeightSnapshot> */
    public array $weights = [];

    /** @var list<HorseFeedingPlanItemSnapshot> */
    public array $feedingPlan = [];

    public function getTitle(): string|Htmlable
    {
        return __('owner/horse_care.page.title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/owner/horses') => __('owner/horses.navigation'),
            url('/owner/horses/'.$this->centralHorseId.'/details') => __('owner/horse_detail.breadcrumb'),
            __('owner/horse_care.page.breadcrumb') => '',
        ];
    }

    public function mount(string $centralHorseId): void
    {
        $this->centralHorseId = $centralHorseId;
        $user = Auth::user();
        abort_unless($user !== null, 401);

        try {
            $active = app(HorseOwnerStableAccessGate::class)->tryAuthorize($user, $centralHorseId);
        } catch (AuthorizationException) {
            abort(403, __('owner/horse_care.access.denied'));
        }

        if ($active !== null) {
            $this->assignment = $active;
        } else {
            // Ended → read-only fallback (Q3 z roadmap).
            $ended = HorseBoardingAssignment::query()
                ->where('central_horse_id', $centralHorseId)
                ->where('owner_user_id', $user->id)
                ->where('status', HorseBoardingAssignment::STATUS_ENDED)
                ->latest('started_at')
                ->first();
            if ($ended === null) {
                abort(403, __('owner/horse_care.access.denied'));
            }
            $this->assignment = $ended;
        }

        $this->stableTenant = Tenant::query()->find($this->assignment->stable_tenant_id);
        abort_unless($this->stableTenant !== null, 404);

        $service = app(OwnerHorseCareService::class);
        $this->weights = $service->weightsForHorse($centralHorseId, $this->stableTenant);
        $this->feedingPlan = $service->feedingPlanForHorse($centralHorseId, $this->stableTenant);
    }

    /**
     * Formatowanie delta wagi: "+2,3 kg" / "-1,5 kg" / "—" dla pierwszego.
     */
    public function formatDelta(?float $delta): string
    {
        if ($delta === null) {
            return '—';
        }
        $sign = $delta > 0 ? '+' : '';

        return $sign.number_format($delta, 1, ',', '').' kg';
    }

    /**
     * Color cue dla trendu: gain duże = success, loss duże = warning,
     * <5kg fluctuation = gray (normalny dobowy szum).
     */
    public function deltaColorClass(?float $delta): string
    {
        if ($delta === null || abs($delta) < 5) {
            return 'text-gray-500';
        }

        return $delta > 0 ? 'text-emerald-600' : 'text-amber-600';
    }

    public function formatWeight(float $weightKg): string
    {
        return number_format($weightKg, 1, ',', ' ').' kg';
    }

    public function latestWeight(): ?HorseWeightSnapshot
    {
        // weights ordered ASC by measured_at — last is latest.
        $count = count($this->weights);

        return $count > 0 ? $this->weights[$count - 1] : null;
    }
}
