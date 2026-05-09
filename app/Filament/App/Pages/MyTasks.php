<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Specialist;
use App\Tenancy\TenantManager;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Moje zadania" — landing dla pracownika-specjalisty (kowal /
 * weterynarz). Widoczna tylko gdy zalogowany użytkownik ma
 * przypisany rekord Specialist (po central_user_id).
 *
 * Pokazuje 3 sekcje wyciągnięte z health_records, filtrowane po
 * specialist_id zalogowanego użytkownika:
 *   1. Przeterminowane — next_due_at < dziś
 *   2. Najbliższe zabiegi — next_due_at w nadchodzących 30 dniach
 *   3. Ostatnio wykonane — performed_at w ostatnich 30 dniach
 */
class MyTasks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.my_tasks.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.my_tasks.title');
    }

    protected static string $view = 'filament.app.pages.my-tasks';

    /**
     * Page is invisible to users without a linked Specialist record —
     * "Moje zadania" makes no sense for stable owner / accountant.
     */
    public static function canAccess(): bool
    {
        return self::specialistForUser() !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    private static function specialistForUser(): ?Specialist
    {
        $userId = Auth::id();
        if (! $userId) {
            return null;
        }

        // Filament evaluates shouldRegisterNavigation()/canAccess() during
        // sidebar render, which can fire before InitialiseTenantFromSession
        // has bound a tenant connection (e.g. login → /app redirect → tenant
        // selector). Querying the tenant DB at that moment hits an empty
        // ''@'localhost' connection. Bail silently and let the page reappear
        // after the user picks a stable.
        if (! app(TenantManager::class)->hasTenant()) {
            return null;
        }

        try {
            return Specialist::query()
                ->where('central_user_id', $userId)
                ->where('is_active', true)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function specialist(): ?Specialist
    {
        return self::specialistForUser();
    }

    /** @return Collection<int, HealthRecord> */
    public function overdue(): Collection
    {
        $specialist = $this->specialist();
        if (! $specialist) {
            return collect();
        }

        return HealthRecord::query()
            ->where('specialist_id', $specialist->id)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now()->toDateString())
            ->with('horse:id,name')
            ->orderBy('next_due_at')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, HealthRecord> */
    public function upcoming(): Collection
    {
        $specialist = $this->specialist();
        if (! $specialist) {
            return collect();
        }

        return HealthRecord::query()
            ->where('specialist_id', $specialist->id)
            ->whereNotNull('next_due_at')
            ->whereBetween('next_due_at', [
                now()->toDateString(),
                now()->addDays(30)->toDateString(),
            ])
            ->with('horse:id,name')
            ->orderBy('next_due_at')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, HealthRecord> */
    public function recent(): Collection
    {
        $specialist = $this->specialist();
        if (! $specialist) {
            return collect();
        }

        return HealthRecord::query()
            ->where('specialist_id', $specialist->id)
            ->where('performed_at', '>=', now()->subDays(30))
            ->with('horse:id,name')
            ->orderByDesc('performed_at')
            ->limit(20)
            ->get();
    }

    public function daysFromNow(?Carbon $date): ?int
    {
        if (! $date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($date, false);
    }
}
