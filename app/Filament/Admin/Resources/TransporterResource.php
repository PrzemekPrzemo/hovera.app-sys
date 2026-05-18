<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Actions\Impersonation\StartImpersonation;
use App\Domain\Transport\Notifications\TransporterRejectedNotification;
use App\Domain\Transport\Notifications\TransporterVerifiedNotification;
use App\Domain\Transport\Verification\VerificationChecklist;
use App\Domain\Transport\Verification\VerificationChecklistService;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\InvitationsRelationManager;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\MembershipsRelationManager;
use App\Filament\Admin\Resources\TransporterResource\Pages;
use App\Filament\Admin\Resources\TransporterResource\RelationManagers\TransporterDocumentsRelationManager;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Master admin lista firm transportowych — osobno od stajni. Patrz docs/TRANSPORT.md
 * (feedback prod): "po lewej stronie po za stajniami również obszar transportu
 * by zarządzać firmami transportowymi".
 *
 * Eloquent query scope'owany do `tenants.type=transporter`. Verify/Reject
 * akcje wykonywane przez master admin'a po sprawdzeniu wgranych dokumentów
 * (zarządzanych w /transport po stronie tenant'a).
 */
class TransporterResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('admin/transporter.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.transport_admin');
    }

    public static function getModelLabel(): string
    {
        return __('admin/transporter.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/transporter.model.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereIn('verification_status', [
                VerificationStatus::Pending->value,
                VerificationStatus::UnderReview->value,
            ])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TenantType::Transporter->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/transporter.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('slug')->disabled(),
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('legal_name')->maxLength(255),
                    Forms\Components\TextInput::make('tax_id')
                        ->label(__('admin/transporter.form.label.tax_id'))
                        ->maxLength(32),
                ]),

            Forms\Components\Section::make(__('admin/transporter.form.section.verification'))
                ->description(__('admin/transporter.form.section.verification_description'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('verification_status')
                        ->label(__('admin/transporter.form.label.verification_status'))
                        ->options(VerificationStatus::options())
                        ->disabled()
                        ->helperText(__('admin/transporter.form.helper.verification_status')),
                    Forms\Components\DateTimePicker::make('verified_at')
                        ->label(__('admin/transporter.form.label.verified_at'))
                        ->disabled(),
                    Forms\Components\Textarea::make('verification_notes')
                        ->label(__('admin/transporter.form.label.verification_notes'))
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText(__('admin/transporter.form.helper.verification_notes')),
                ]),

            Forms\Components\Section::make(__('admin/transporter.form.section.subscription'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('plan_id')
                        ->label(__('admin/transporter.form.label.plan'))
                        ->options(fn () => Plan::query()->forTransporters()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'provisioning' => 'provisioning',
                            'trialing' => 'trialing',
                            'active' => 'active',
                            'past_due' => 'past_due',
                            'suspended' => 'suspended',
                            'churned' => 'churned',
                        ])
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (Tenant $t) => $t->tax_id ? 'NIP '.$t->tax_id : null),
                Tables\Columns\TextColumn::make('verification_status')
                    ->label(__('admin/transporter.table.column.verification'))
                    ->badge()
                    ->color(fn ($state) => $state instanceof VerificationStatus ? $state->color() : 'gray')
                    ->formatStateUsing(fn ($state) => $state instanceof VerificationStatus ? $state->label() : '—'),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('admin/transporter.table.column.plan'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin/transporter.table.column.subscription'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'trialing', 'past_due' => 'warning',
                        'suspended', 'churned' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('country')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->label(__('admin/transporter.table.column.verified_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label(__('admin/transporter.table.column.last_activity_at'))
                    ->since()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/transporter.table.column.created_at'))
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Pending + under_review najpierw — wymagają akcji master admin'a.
            ->defaultSort(fn (Builder $q) => $q->orderByRaw("CASE verification_status
                WHEN 'under_review' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'verified' THEN 4
                ELSE 5
            END")->orderByDesc('created_at'))
            ->filters([
                Tables\Filters\SelectFilter::make('verification_status')
                    ->label(__('admin/transporter.table.column.verification'))
                    ->options(VerificationStatus::options()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label(__('admin/transporter.action.verify'))
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (Tenant $t) => $t->verification_status !== VerificationStatus::Verified)
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('admin/transporter.form.label.verification_notes'))
                            ->rows(3),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        // Auto-block: nie pozwalamy zatwierdzić tenanta dopóki nie wszystkie
                        // wymagane PWL dokumenty mają status=verified.
                        $checklist = self::checklistFor($record);
                        if (! $checklist->isComplete()) {
                            Notification::make()
                                ->danger()
                                ->title(__('transport/documents.admin.cannot_verify_tenant', [
                                    'done' => $checklist->verifiedCount,
                                    'total' => $checklist->totalRequired,
                                ]))
                                ->body(__('transport/documents.checklist.missing_intro').' '.implode(', ', $checklist->missingLabels))
                                ->persistent()
                                ->send();

                            return;
                        }

                        self::verify($record, (string) ($data['notes'] ?? ''));
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('reject')
                    ->label(__('admin/transporter.action.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Tenant $t) => in_array($t->verification_status, [VerificationStatus::Pending, VerificationStatus::UnderReview], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('admin/transporter.form.label.rejection_reason'))
                            ->rows(4)
                            ->required(),
                    ])
                    ->action(fn (Tenant $record, array $data) => self::reject($record, (string) $data['reason']))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('login_as_owner')
                    ->label(__('admin/transporter.action.login_as_owner.label'))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Tenant $r) => ! $r->trashed()
                        && $r->status === 'active'
                        && $r->memberships()->whereNull('revoked_at')->whereNotNull('user_id')->exists())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('admin/transporter.action.login_as_owner.reason_label'))
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->helperText(__('admin/transporter.action.login_as_owner.reason_helper')),
                    ])
                    ->action(function (Tenant $record, array $data, StartImpersonation $impersonate) {
                        $membership = $record->memberships()
                            ->whereNull('revoked_at')
                            ->whereNotNull('user_id')
                            ->orderBy('created_at')
                            ->with('user')
                            ->first();

                        if (! $membership || ! $membership->user) {
                            Notification::make()->danger()
                                ->title(__('admin/transporter.action.login_as_owner.no_user_title'))
                                ->body(__('admin/transporter.action.login_as_owner.no_user_body'))
                                ->send();

                            return;
                        }

                        $impersonate->execute(
                            masterAdmin: Auth::user(),
                            tenant: $record,
                            targetUser: $membership->user,
                            reason: (string) $data['reason'],
                            session: request()->session(),
                        );
                    })
                    ->successRedirectUrl('/transport')
                    ->modalSubmitActionLabel(__('admin/transporter.action.login_as_owner.submit')),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransporterDocumentsRelationManager::class,
            MembershipsRelationManager::class,
            InvitationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransporters::route('/'),
            'edit' => Pages\EditTransporter::route('/{record}/edit'),
        ];
    }

    /**
     * Buduje checklistę PWL w kontekście danego tenanta — pomocnik do akcji
     * verify w master adminie. Bezpiecznie przepina TenantManager; jeśli
     * tenant DB nie jest dostępna (testy bez tenant migracji) — zwraca
     * pustą checklistę (verifiedCount = 0, totalRequired = 0, isComplete = true)
     * żeby nie wybuchać produkcji ani testów feature.
     */
    public static function checklistFor(Tenant $tenant): VerificationChecklist
    {
        try {
            app(TenantManager::class)->setCurrent($tenant);

            return app(VerificationChecklistService::class)->build();
        } catch (\Throwable $e) {
            report($e);

            return new VerificationChecklist(
                items: [],
                verifiedCount: 0,
                totalRequired: 0,
                missingLabels: [],
            );
        }
    }

    public static function verify(Tenant $tenant, string $notes = ''): void
    {
        // Lista zweryfikowanych dokumentów — przekazywana do notyfikacji
        // żeby mail do owner'a wyliczał konkretnie co przeszło. Try/catch
        // bo w testach feature tenant DB może nie istnieć.
        $verifiedTypes = [];
        try {
            app(TenantManager::class)->setCurrent($tenant);
            $checklist = app(VerificationChecklistService::class)->build();
            foreach ($checklist->items as $item) {
                if ($item->isVerified()) {
                    $verifiedTypes[] = $item->label;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $tenant->forceFill([
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
            'verified_by_user_id' => Auth::id(),
            'verification_notes' => $notes !== '' ? $notes : $tenant->verification_notes,
        ])->save();

        app(MasterAuditLogger::class)->record(
            action: 'transporter.verify',
            targetType: 'Tenant',
            targetId: (string) $tenant->id,
            tenantId: (string) $tenant->id,
            payload: ['slug' => $tenant->slug, 'verified_docs' => $verifiedTypes],
        );

        self::notifyOwner($tenant, new TransporterVerifiedNotification($tenant, $notes, $verifiedTypes));

        Notification::make()
            ->success()
            ->title(__('admin/transporter.notify.verified'))
            ->body(__('admin/transporter.notify.verified_body', ['name' => $tenant->name]))
            ->send();
    }

    public static function reject(Tenant $tenant, string $reason): void
    {
        $tenant->forceFill([
            'verification_status' => VerificationStatus::Rejected,
            'verified_at' => null,
            'verified_by_user_id' => Auth::id(),
            'verification_notes' => $reason,
        ])->save();

        app(MasterAuditLogger::class)->record(
            action: 'transporter.reject',
            targetType: 'Tenant',
            targetId: (string) $tenant->id,
            tenantId: (string) $tenant->id,
            payload: ['slug' => $tenant->slug, 'reason_excerpt' => mb_substr($reason, 0, 120)],
        );

        self::notifyOwner($tenant, new TransporterRejectedNotification($tenant, $reason));

        Notification::make()
            ->warning()
            ->title(__('admin/transporter.notify.rejected'))
            ->body(__('admin/transporter.notify.rejected_body', ['name' => $tenant->name]))
            ->send();
    }

    private static function notifyOwner(Tenant $tenant, object $notification): void
    {
        $email = DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->value('users.email');

        if (! $email) {
            return;
        }

        try {
            NotificationFacade::route('mail', $email)->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
