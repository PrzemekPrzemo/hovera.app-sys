<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Notifications\Boarding\HorseBoardingRequestedNotification;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * PR 3 z TODO.md — Owner marketplace: lista stajni + request boardingu.
 *
 * Owner widzi public listę stajni (Tenant where type=stable, status
 * active/trialing/past_due) z basic info: name, slug, plan, kraj.
 *
 * Klik "Wyślij prośbę o boarding" → action modal z picker'em konia
 * (CentralHorseRegistry where primary_owner_user_id=Auth::id()) →
 * submit:
 *   - `HorseRegistrySyncService::requestBoarding()` idempotent
 *     (status=pending)
 *   - Dispatch `HorseBoardingRequestedNotification` do stable team
 *     members (owner/admin/manager roles) — soft-fail
 *   - Notification success
 *
 * Stable team odbiera w `/app/pending-boarding-requests` (kolejne PR
 * 3-część-druga albo nowy follow-up — to nie jest w tym PR).
 */
class StableMarketplace extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.owner.pages.stable-marketplace';

    protected static ?string $slug = 'stables';

    public function getTitle(): string|Htmlable
    {
        return __('owner/stable_marketplace.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/stable_marketplace.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('owner/stable_marketplace.navigation_group');
    }

    /**
     * @return Collection<int,Tenant>
     */
    public function stables(): Collection
    {
        return Tenant::query()
            ->where('type', TenantType::Stable->value)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'country', 'plan_id']);
    }

    public function requestBoardingAction(): Action
    {
        return Action::make('requestBoarding')
            ->label(__('owner/stable_marketplace.action.request_boarding'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->modalHeading(fn (array $arguments) => __('owner/stable_marketplace.action.modal_heading', [
                'stable' => $arguments['stable_name'] ?? '—',
            ]))
            ->form([
                Forms\Components\Select::make('central_horse_id')
                    ->label(__('owner/stable_marketplace.action.horse_label'))
                    ->options(fn () => $this->ownerHorseOptions())
                    ->required()
                    ->searchable()
                    ->helperText(__('owner/stable_marketplace.action.horse_helper')),
            ])
            ->action(function (array $data, array $arguments) {
                $stableId = (string) ($arguments['stable_id'] ?? '');
                $stable = Tenant::query()
                    ->where('id', $stableId)
                    ->where('type', TenantType::Stable->value)
                    ->whereIn('status', ['active', 'trialing', 'past_due'])
                    ->first();

                if ($stable === null) {
                    Notification::make()->danger()
                        ->title(__('owner/stable_marketplace.action.stable_missing'))
                        ->send();

                    return;
                }

                $horse = CentralHorseRegistry::query()
                    ->where('id', $data['central_horse_id'])
                    ->where('primary_owner_user_id', Auth::id())  // anti-injection
                    ->first();

                if ($horse === null) {
                    Notification::make()->danger()
                        ->title(__('owner/stable_marketplace.action.horse_missing'))
                        ->send();

                    return;
                }

                $assignment = app(HorseRegistrySyncService::class)
                    ->requestBoarding($horse, $stable, Auth::user());

                // Dispatch notification do stable team (owner/admin/manager).
                self::dispatchStableTeamNotification($stable, $horse, $assignment);

                Notification::make()->success()
                    ->title(__('owner/stable_marketplace.action.success'))
                    ->body(__('owner/stable_marketplace.action.success_body', [
                        'horse' => $horse->name,
                        'stable' => $stable->name,
                    ]))
                    ->send();
            });
    }

    /**
     * @return array<string,string>
     */
    private function ownerHorseOptions(): array
    {
        return CentralHorseRegistry::query()
            ->where('primary_owner_user_id', Auth::id())
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CentralHorseRegistry $h) => [
                $h->id => sprintf(
                    '%s (%s)',
                    $h->name,
                    $h->passport_no ?: __('owner/stable_marketplace.action.no_passport'),
                ),
            ])
            ->all();
    }

    /**
     * Dispatch HorseBoardingRequestedNotification do team members
     * stajni (owner/admin/manager). Soft-fail.
     */
    private static function dispatchStableTeamNotification(
        Tenant $stable,
        CentralHorseRegistry $horse,
        HorseBoardingAssignment $assignment,
    ): void {
        try {
            $teamUserIds = TenantMembership::query()
                ->where('tenant_id', $stable->id)
                ->whereIn('role', ['owner', 'admin', 'manager'])
                ->whereNull('revoked_at')
                ->pluck('user_id')
                ->all();

            if ($teamUserIds === []) {
                return;
            }

            $users = User::query()->whereIn('id', $teamUserIds)->get();
            if ($users->isEmpty()) {
                return;
            }

            NotificationFacade::send($users, new HorseBoardingRequestedNotification(
                assignmentId: (string) $assignment->id,
                stableTenantId: (string) $stable->id,
                stableName: (string) $stable->name,
                centralHorseId: (string) $horse->id,
                horseName: (string) $horse->name,
                // Stable team idzie do swojego pending list (PR 3 part 2 doda widok).
                // Na razie link do stable horses panel — team może tam obejrzeć stan
                // (po PR 3.5 / future iteration → /app/pending-boarding-requests).
                ownerPanelUrl: url('/app/horses'),
            ));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Owner-initiated boarding stable team notification failed (soft-fail)', [
                'stable_tenant_id' => $stable->id,
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
