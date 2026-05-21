<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Filament\Owner\Resources\PendingBoardingRequestResource\Pages;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\Horse;
use App\Notifications\Boarding\HorseBoardingAcceptedNotification;
use App\Notifications\Boarding\HorseBoardingRejectedNotification;
use App\Tenancy\TenantManager;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PR 2 z TODO.md — Owner accept/reject pending boarding requests.
 *
 * Stable initiates via `/app/horses` → "Importuj z rejestru" (PR 1)
 * → tworzy `HorseBoardingAssignment.pending`. Właściciel widzi to
 * w swoim panelu jako lista "Stajnie czekają na Twoją zgodę".
 *
 * Accept → `activateBoarding()` (status=active, started_at=now()) +
 * TenantManager::execute do stable tenant'a → tworzymy/aktualizujemy
 * `Horse` row z `central_horse_id` linkiem, dzięki czemu stable
 * natychmiast widzi konia w swojej liście.
 *
 * Reject → status=disputed + rejection_reason (logged via central audit).
 * Stable widzi w przyszłej iteracji jako "odrzucone request'y" (PR 3+).
 */
class PendingBoardingRequestResource extends Resource
{
    protected static ?string $model = HorseBoardingAssignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('owner/pending_boarding.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('owner/pending_boarding.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('owner/pending_boarding.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/pending_boarding.model.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        return parent::getEloquentQuery()
            ->where('owner_user_id', $userId ?? '_no_user_')
            ->where('status', HorseBoardingAssignment::STATUS_PENDING)
            ->with(['horse:id,name,passport_no', 'stable:id,name,slug']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('horse.name')
                    ->label(__('owner/pending_boarding.table.column.horse'))
                    ->searchable(query: function (Builder $q, string $search) {
                        $q->whereHas('horse', fn ($qh) => $qh->where('name', 'like', "%{$search}%"));
                    })
                    ->description(fn (HorseBoardingAssignment $r) => $r->horse?->passport_no
                        ? __('owner/pending_boarding.table.passport_prefix').' '.$r->horse->passport_no
                        : __('owner/pending_boarding.table.no_passport')),
                Tables\Columns\TextColumn::make('stable.name')
                    ->label(__('owner/pending_boarding.table.column.stable'))
                    ->searchable(query: function (Builder $q, string $search) {
                        $q->whereHas('stable', fn ($qt) => $qt->where('name', 'like', "%{$search}%"));
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('owner/pending_boarding.table.column.requested_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('owner/pending_boarding.empty.heading'))
            ->emptyStateDescription(__('owner/pending_boarding.empty.description'))
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label(__('owner/pending_boarding.action.accept.label'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (HorseBoardingAssignment $r) => __('owner/pending_boarding.action.accept.modal_description', [
                        'horse' => $r->horse?->name ?? '—',
                        'stable' => $r->stable?->name ?? '—',
                    ]))
                    ->action(fn (HorseBoardingAssignment $r) => self::handleAccept($r)),
                Tables\Actions\Action::make('reject')
                    ->label(__('owner/pending_boarding.action.reject.label'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('owner/pending_boarding.action.reject.reason_label'))
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                    ])
                    ->action(fn (HorseBoardingAssignment $r, array $data) => self::handleReject($r, (string) $data['reason'])),
            ]);
    }

    /**
     * Accept flow: activate central assignment + materialize Horse w
     * stable tenant DB (jeśli jeszcze nie ma).
     */
    public static function handleAccept(HorseBoardingAssignment $assignment): void
    {
        $service = app(HorseRegistrySyncService::class);
        $service->activateBoarding($assignment);

        $stable = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stable === null) {
            Notification::make()->danger()
                ->title(__('owner/pending_boarding.action.accept.stable_missing'))
                ->send();

            return;
        }

        // Materialize Horse w stable tenant DB (cross-tenant switch).
        // Jeśli już istnieje z tym central_horse_id (np. wcześniejszy
        // boarding ended) — skipujemy create, tylko aktywujemy.
        $registry = $assignment->horse;
        try {
            app(TenantManager::class)->execute($stable, function () use ($assignment, $registry) {
                $exists = Horse::query()
                    ->where('central_horse_id', $assignment->central_horse_id)
                    ->exists();

                if (! $exists && $registry !== null) {
                    Horse::create([
                        'id' => (string) Str::ulid(),
                        'central_horse_id' => $assignment->central_horse_id,
                        'name' => (string) $registry->name,
                        'passport_number' => $registry->passport_no,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // Stable DB sync failed — assignment już active w central,
            // ale Horse nie utworzony. Loguj — ops backfilluje ręcznie.
            report($e);
            Log::warning('Failed to materialize Horse in stable tenant after boarding accept', [
                'assignment_id' => $assignment->id,
                'stable_tenant_id' => $stable->id,
                'central_horse_id' => $assignment->central_horse_id,
                'error' => $e->getMessage(),
            ]);
        }

        // PR 5 — dispatch HorseBoardingAcceptedNotification do team
        // members stajni (owner/admin/manager). Soft-fail.
        self::dispatchStableTeamNotification(
            $stable,
            new HorseBoardingAcceptedNotification(
                assignmentId: (string) $assignment->id,
                ownerName: (string) (Auth::user()?->name ?? Auth::user()?->email ?? '—'),
                ownerEmail: (string) (Auth::user()?->email ?? '—'),
                centralHorseId: (string) $assignment->central_horse_id,
                horseName: (string) ($registry?->name ?? '—'),
                stableHorseUrl: url('/app/horses'),
            ),
        );

        Notification::make()->success()
            ->title(__('owner/pending_boarding.action.accept.success'))
            ->body(__('owner/pending_boarding.action.accept.success_body', [
                'stable' => $stable->name,
            ]))
            ->send();
    }

    public static function handleReject(HorseBoardingAssignment $assignment, string $reason): void
    {
        $assignment->forceFill([
            'status' => HorseBoardingAssignment::STATUS_DISPUTED,
        ])->save();

        Log::info('Owner rejected boarding request', [
            'assignment_id' => $assignment->id,
            'stable_tenant_id' => $assignment->stable_tenant_id,
            'central_horse_id' => $assignment->central_horse_id,
            'reason' => $reason,
        ]);

        // PR 5 — dispatch HorseBoardingRejectedNotification do team members
        // stajni. Soft-fail.
        $stable = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stable !== null) {
            self::dispatchStableTeamNotification(
                $stable,
                new HorseBoardingRejectedNotification(
                    assignmentId: (string) $assignment->id,
                    ownerName: (string) (Auth::user()?->name ?? Auth::user()?->email ?? '—'),
                    ownerEmail: (string) (Auth::user()?->email ?? '—'),
                    centralHorseId: (string) $assignment->central_horse_id,
                    horseName: (string) ($assignment->horse?->name ?? '—'),
                    reason: $reason,
                ),
            );
        }

        Notification::make()->success()
            ->title(__('owner/pending_boarding.action.reject.success'))
            ->send();
    }

    /**
     * Dispatch notification do team members stajni (role: owner/admin/
     * manager). Soft-fail: SMTP padu nie blokuje owner UI.
     */
    private static function dispatchStableTeamNotification(Tenant $stable, \Illuminate\Notifications\Notification $notification): void
    {
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

            \Illuminate\Support\Facades\Notification::send($users, $notification);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Boarding stable team notification dispatch failed (soft-fail)', [
                'stable_tenant_id' => $stable->id,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function canCreate(): bool
    {
        // Boarding request są inicjowane przez stable (PR 1) lub owner
        // (PR 3 w przyszłości). Tu owner tylko accept/reject, nie create.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendingBoardingRequests::route('/'),
        ];
    }
}
