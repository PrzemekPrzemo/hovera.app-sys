<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Enums\TenantType;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Vehicle;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Strona „Moja subskrypcja" w panelu transportera (/transport/my-subscription).
 *
 * Pokazuje:
 *   1. Aktualny plan (Start / Pro / Business / Enterprise) + cykl billing + status
 *   2. Zużycie limitów (vehicles X/Y, drivers X/Y) z color-coded progress bars
 *   3. Plan upgrade grid — 4 plany transport audience, current highlighted,
 *      pozostałe z buttonem „Wybierz" prowadzącym do `/app/billing/checkout`
 *      (endpoint już resolve'uje tenant z sesji, więc działa cross-panel)
 *   4. Sponsored placements — lista zakupionych addonów + CTA „Kup wyróżnienie"
 *      (link do master admina; obecnie master admin generuje P24 link)
 *
 * Role gate: owner + admin (subskrypcję zmienia tylko właściciel firmy).
 */
class MySubscription extends Page
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationLabel(): string
    {
        return __('transport/subscription.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/subscription.title');
    }

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.transport.pages.my-subscription';

    /**
     * Dane do view'a — current plan, usage, dostępne plany, addon history.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant instanceof Tenant) {
            return [
                'tenant' => null,
                'currentPlan' => null,
                'availablePlans' => collect(),
                'usage' => $this->emptyUsage(),
                'addonPurchases' => collect(),
            ];
        }

        $currentPlan = $tenant->plan;

        // Tylko publiczne, aktywne plany transport audience. Enterprise w
        // seeder'ze ma price_monthly_cents=0 i is_public=true — ale pokazujemy
        // go też (klient widzi że istnieje, „Skontaktuj się z nami").
        $availablePlans = Plan::query()
            ->where('audience', TenantType::Transporter->value)
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();

        $usage = $this->collectUsage($tenant, $currentPlan);

        $addonPurchases = AddonPurchase::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'tenant' => $tenant,
            'currentPlan' => $currentPlan,
            'availablePlans' => $availablePlans,
            'usage' => $usage,
            'addonPurchases' => $addonPurchases,
        ];
    }

    /**
     * Zlicza zużycie limitów tenant'a — vehicles i drivers count vs plan limits.
     * `-1` w plan limit = unlimited (Enterprise plan).
     *
     * @return array{
     *   vehicles:array{used:int, limit:int|null, percent:?int, near_limit:bool},
     *   drivers:array{used:int, limit:int|null, percent:?int, near_limit:bool},
     * }
     */
    private function collectUsage(Tenant $tenant, ?Plan $plan): array
    {
        $vehiclesUsed = (int) Vehicle::query()->count();
        $driversUsed = (int) Driver::query()->count();

        $vehiclesLimit = $plan ? (int) ($plan->limits['max_vehicles'] ?? 0) : 0;
        $driversLimit = $plan ? (int) ($plan->limits['max_drivers'] ?? 0) : 0;

        return [
            'vehicles' => $this->normalizeUsage($vehiclesUsed, $vehiclesLimit),
            'drivers' => $this->normalizeUsage($driversUsed, $driversLimit),
        ];
    }

    /**
     * @return array{used:int, limit:int|null, percent:?int, near_limit:bool}
     */
    private function normalizeUsage(int $used, int $limit): array
    {
        // -1 = unlimited (Enterprise). Pokazujemy used bez procenta.
        if ($limit < 0) {
            return ['used' => $used, 'limit' => null, 'percent' => null, 'near_limit' => false];
        }

        if ($limit === 0) {
            return ['used' => $used, 'limit' => 0, 'percent' => null, 'near_limit' => false];
        }

        $percent = (int) min(100, round($used / $limit * 100));

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $percent,
            'near_limit' => $percent >= 80,
        ];
    }

    /**
     * @return array{
     *   vehicles:array{used:int, limit:?int, percent:?int, near_limit:bool},
     *   drivers:array{used:int, limit:?int, percent:?int, near_limit:bool},
     * }
     */
    private function emptyUsage(): array
    {
        return [
            'vehicles' => ['used' => 0, 'limit' => null, 'percent' => null, 'near_limit' => false],
            'drivers' => ['used' => 0, 'limit' => null, 'percent' => null, 'near_limit' => false],
        ];
    }
}
