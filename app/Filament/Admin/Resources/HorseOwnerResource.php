<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Actions\Impersonation\StartImpersonation;
use App\Enums\TenantType;
use App\Filament\Admin\Resources\HorseOwnerResource\Pages;
use App\Filament\Admin\Resources\TenantResource\RelationManagers\MembershipsRelationManager;
use App\Models\Central\Tenant;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * Master admin: dedykowana lista właścicieli koni (`tenants.type=horse_owner`).
 *
 * Wcześniej horse owners byli widoczni TYLKO w głównym TenantResource (mylące
 * "Stajnie" navigation + form stable-centric). Tutaj scope'owany resource z
 * prostym formem (slug/name/status read-mostly), surfaced'owanym email'em
 * ownera + actions:
 *   - force_password_reset (sendResetLink na owner.email z central User)
 *   - login_as_owner (impersonacja → redirect na /owner panel)
 *
 * Form pomija: plan (owner_free hardcoded), KSeF, GUS, RODO/sąd context —
 * to nieaplikowalne do free consumer tier. Owner sees /owner panel
 * (TenantType::HorseOwner::panelId() = 'owner').
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "self-service rejestracja".
 */
class HorseOwnerResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('admin/horse_owner.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin/horse_owner.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('admin/horse_owner.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/horse_owner.model.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TenantType::HorseOwner->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/horse_owner.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin/horse_owner.form.label.name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('admin/horse_owner.form.label.slug'))
                        ->disabled()
                        ->helperText(__('admin/horse_owner.form.helper.slug')),
                    Forms\Components\Select::make('status')
                        ->label(__('admin/horse_owner.form.label.status'))
                        ->options([
                            'active' => __('admin/horse_owner.form.option.status.active'),
                            'suspended' => __('admin/horse_owner.form.option.status.suspended'),
                        ])
                        ->required(),
                    Forms\Components\DateTimePicker::make('terms_accepted_at')
                        ->label(__('admin/horse_owner.form.label.terms_accepted_at'))
                        ->disabled()
                        ->seconds(false),
                ]),

            Forms\Components\Section::make(__('admin/horse_owner.form.section.owner_account'))
                ->description(__('admin/horse_owner.form.section.owner_account_description'))
                ->schema([
                    Forms\Components\Placeholder::make('owner_email')
                        ->label(__('admin/horse_owner.form.label.owner_email'))
                        ->content(fn (?Tenant $record) => self::ownerEmail($record) ?? '—'),
                    Forms\Components\Placeholder::make('owner_phone')
                        ->label(__('admin/horse_owner.form.label.owner_phone'))
                        ->content(fn (?Tenant $record) => $record?->settings['contact']['phone'] ?? '—'),
                ]),

            Forms\Components\Section::make(__('admin/horse_owner.form.section.metadata'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('country')
                        ->label(__('admin/horse_owner.form.label.country'))
                        ->disabled(),
                    Forms\Components\TextInput::make('locale')
                        ->label(__('admin/horse_owner.form.label.locale'))
                        ->disabled(),
                    Forms\Components\TextInput::make('timezone')
                        ->label(__('admin/horse_owner.form.label.timezone'))
                        ->disabled(),
                    Forms\Components\DateTimePicker::make('created_at')
                        ->label(__('admin/horse_owner.form.label.created_at'))
                        ->disabled()
                        ->seconds(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/horse_owner.table.column.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner_email')
                    ->label(__('admin/horse_owner.table.column.owner_email'))
                    ->getStateUsing(fn (Tenant $record) => self::ownerEmail($record) ?? '—')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('memberships.user', fn ($q) => $q->where('email', 'like', "%{$search}%"));
                    })
                    ->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin/horse_owner.table.column.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin/horse_owner.table.column.slug'))
                    ->toggleable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/horse_owner.table.column.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('admin/horse_owner.form.option.status.active'),
                        'suspended' => __('admin/horse_owner.form.option.status.suspended'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('force_password_reset')
                    ->label(__('admin/horse_owner.action.force_password_reset.label'))
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (Tenant $r) => self::ownerEmail($r) !== null)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Tenant $r) => __('admin/horse_owner.action.force_password_reset.modal_description', [
                        'email' => self::ownerEmail($r) ?? '—',
                    ]))
                    ->action(function (Tenant $record, MasterAuditLogger $audit) {
                        $email = self::ownerEmail($record);
                        if ($email === null) {
                            Notification::make()->danger()
                                ->title(__('admin/horse_owner.action.force_password_reset.no_owner'))
                                ->send();

                            return;
                        }

                        $status = Password::sendResetLink(['email' => $email]);

                        $audit->record(
                            'horse_owner.force_password_reset',
                            'Tenant',
                            $record->id,
                            $record->id,
                            ['owner_email' => $email, 'status' => $status],
                        );

                        if ($status === Password::RESET_LINK_SENT) {
                            Notification::make()->success()
                                ->title(__('admin/horse_owner.action.force_password_reset.success'))
                                ->body(__('admin/horse_owner.action.force_password_reset.success_body', ['email' => $email]))
                                ->send();
                        } else {
                            Notification::make()->danger()
                                ->title(__('admin/horse_owner.action.force_password_reset.failed'))
                                ->body((string) $status)
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('login_as_owner')
                    ->label(__('admin/horse_owner.action.login_as_owner.label'))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Tenant $r) => $r->status === 'active'
                        && $r->memberships()->whereNull('revoked_at')->whereNotNull('user_id')->exists())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('admin/horse_owner.action.login_as_owner.reason_label'))
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->helperText(__('admin/horse_owner.action.login_as_owner.reason_helper')),
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
                                ->title(__('admin/horse_owner.action.login_as_owner.no_user_title'))
                                ->body(__('admin/horse_owner.action.login_as_owner.no_user_body'))
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
                    ->successRedirectUrl('/owner')
                    ->modalSubmitActionLabel(__('admin/horse_owner.action.login_as_owner.submit')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorseOwners::route('/'),
            'edit' => Pages\EditHorseOwner::route('/{record}/edit'),
        ];
    }

    /**
     * Pierwszy non-revoked owner email z memberships → linked central User.
     * Centralizujemy lookup żeby form + table + actions miały consistent
     * źródło danych (owner_email lives na User central, NIE na Tenant rowie).
     */
    private static function ownerEmail(?Tenant $tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }

        $membership = $tenant->memberships()
            ->whereNull('revoked_at')
            ->where('role', 'owner')
            ->with('user:id,email')
            ->first();

        return $membership?->user?->email;
    }
}
