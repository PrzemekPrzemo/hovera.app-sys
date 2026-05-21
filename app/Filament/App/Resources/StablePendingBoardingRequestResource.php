<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Filament\App\Resources\StablePendingBoardingRequestResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Notifications\Boarding\HorseBoardingAcceptedNotification;
use App\Notifications\Boarding\HorseBoardingRejectedNotification;
use App\Services\Tenancy\TenantRoleGate;
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
 * PR 3 z TODO.md (part 2) — Stable accept/reject pending boarding'i
 * inicjowane przez owner'a w `/owner/stables` marketplace.
 *
 * Mirror `App\Filament\Owner\Resources\PendingBoardingRequestResource`
 * z odwróconym scopem:
 *   - Owner widzi requesty gdzie owner_user_id=Auth::id() + status=pending
 *   - Stable widzi requesty gdzie stable_tenant_id=current_tenant() + status=pending
 *
 * Accept flow: activate central assignment + materialize Horse w stable
 * tenant DB (identyczna logika do owner-side accept). Reject → status=
 * disputed + dispatch notification do owner'a z powodem.
 */
class StablePendingBoardingRequestResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $model = HorseBoardingAssignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('app/pending_boarding.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getModelLabel(): string
    {
        return __('app/pending_boarding.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app/pending_boarding.model.plural');
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
        $stable = app(TenantManager::class)->current();
        $stableId = $stable?->id ?? '_no_tenant_';

        return parent::getEloquentQuery()
            ->where('stable_tenant_id', $stableId)
            ->where('status', HorseBoardingAssignment::STATUS_PENDING)
            ->with(['horse:id,name,passport_no,primary_owner_user_id', 'owner:id,name,email']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('horse.name')
                    ->label(__('app/pending_boarding.table.column.horse'))
                    ->description(fn (HorseBoardingAssignment $r) => $r->horse?->passport_no
                        ? __('app/pending_boarding.table.passport_prefix').' '.$r->horse->passport_no
                        : __('app/pending_boarding.table.no_passport')),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label(__('app/pending_boarding.table.column.owner'))
                    ->description(fn (HorseBoardingAssignment $r) => $r->owner?->email ?? '—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/pending_boarding.table.column.requested_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('app/pending_boarding.empty.heading'))
            ->emptyStateDescription(__('app/pending_boarding.empty.description'))
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label(__('app/pending_boarding.action.accept.label'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (HorseBoardingAssignment $r) => __('app/pending_boarding.action.accept.modal_description', [
                        'horse' => $r->horse?->name ?? '—',
                        'owner' => $r->owner?->name ?? $r->owner?->email ?? '—',
                    ]))
                    ->action(fn (HorseBoardingAssignment $r) => self::handleAccept($r)),
                Tables\Actions\Action::make('reject')
                    ->label(__('app/pending_boarding.action.reject.label'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('app/pending_boarding.action.reject.reason_label'))
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                    ])
                    ->action(fn (HorseBoardingAssignment $r, array $data) => self::handleReject($r, (string) $data['reason'])),
            ]);
    }

    public static function handleAccept(HorseBoardingAssignment $assignment): void
    {
        $service = app(HorseRegistrySyncService::class);
        $service->activateBoarding($assignment);

        $stable = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stable === null) {
            Notification::make()->danger()
                ->title(__('app/pending_boarding.action.accept.stable_missing'))
                ->send();

            return;
        }

        // Materialize Horse w current tenant — już jesteśmy w stable
        // context (Auth::user() jest stable team), więc tenant connection
        // już ustawiony przez InitialiseTenantFromSession middleware. Ale
        // żeby być pewne, używamy execute() (idempotent — current=stable).
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
            report($e);
            Log::warning('Failed to materialize Horse in stable tenant after stable accept', [
                'assignment_id' => $assignment->id,
                'stable_tenant_id' => $stable->id,
                'central_horse_id' => $assignment->central_horse_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify owner — owner-side equivalent dispatch via
        // HorseBoardingAcceptedNotification.
        try {
            $owner = $assignment->owner;
            if ($owner !== null) {
                $owner->notify(new HorseBoardingAcceptedNotification(
                    assignmentId: (string) $assignment->id,
                    ownerName: (string) ($owner->name ?? $owner->email),
                    ownerEmail: (string) $owner->email,
                    centralHorseId: (string) $assignment->central_horse_id,
                    horseName: (string) ($registry?->name ?? '—'),
                    stableHorseUrl: url('/owner/horses'),
                ));
            }
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Boarding accepted notification to owner failed (soft-fail)', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }

        Notification::make()->success()
            ->title(__('app/pending_boarding.action.accept.success'))
            ->body(__('app/pending_boarding.action.accept.success_body', [
                'horse' => $registry?->name ?? '—',
            ]))
            ->send();
    }

    public static function handleReject(HorseBoardingAssignment $assignment, string $reason): void
    {
        $assignment->forceFill([
            'status' => HorseBoardingAssignment::STATUS_DISPUTED,
        ])->save();

        Log::info('Stable rejected boarding request', [
            'assignment_id' => $assignment->id,
            'stable_tenant_id' => $assignment->stable_tenant_id,
            'central_horse_id' => $assignment->central_horse_id,
            'reason' => $reason,
        ]);

        // Notify owner z powodem.
        try {
            $owner = $assignment->owner;
            $registry = $assignment->horse;
            if ($owner !== null) {
                $stableName = (string) (Tenant::query()->where('id', $assignment->stable_tenant_id)->value('name') ?? '—');
                $owner->notify(new HorseBoardingRejectedNotification(
                    assignmentId: (string) $assignment->id,
                    ownerName: (string) ($owner->name ?? $owner->email),
                    ownerEmail: (string) $owner->email,
                    centralHorseId: (string) $assignment->central_horse_id,
                    horseName: (string) ($registry?->name ?? '—'),
                    reason: $reason.' — '.__('app/pending_boarding.action.reject.from_stable', ['stable' => $stableName]),
                ));
            }
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Boarding rejected notification to owner failed (soft-fail)', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }

        Notification::make()->success()
            ->title(__('app/pending_boarding.action.reject.success'))
            ->send();
    }

    public static function canCreate(): bool
    {
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
            'index' => Pages\ListStablePendingBoardingRequests::route('/'),
        ];
    }
}
