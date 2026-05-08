<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Actions\Memberships\AttachOrInviteUser;
use App\Actions\Memberships\RevokeMembership;
use App\Filament\App\Resources\TeamMemberResource\Pages;
use App\Models\Central\TenantMembership;
use App\Tenancy\TenantManager;
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
 * Pracownicy stajni — owner-side member management. Pokazuje TYLKO
 * członków bieżącej stajni (TenantMembership scoped to current tenant).
 *
 * Dostępne tylko dla użytkowników z rolą owner / admin w bieżącej
 * stajni — analog do master-admin TenantResource → MembershipsRelationManager.
 *
 * Reuse istniejących actions:
 *   - AttachOrInviteUser — dodanie usera (tworzy TenantMembership
 *     jeśli user istnieje, w przeciwnym wypadku UserInvitation)
 *   - RevokeMembership::execute / reactivate — toggle dostępu
 */
class TeamMemberResource extends Resource
{
    protected static ?string $model = TenantMembership::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.team_members');
    }

    public static function getModelLabel(): string
    {
        return __('models.team_member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.team_members');
    }

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return false;
        }
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $tenant->memberships()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    /** @return array<string,string> */
    public static function roleOptions(): array
    {
        return [
            'owner' => __('admin/membership.roles.owner'),
            'admin' => __('admin/membership.roles.admin'),
            'manager' => __('admin/membership.roles.manager'),
            'instructor' => __('admin/membership.roles.instructor'),
            'employee' => __('admin/membership.roles.employee'),
            'vet' => __('admin/membership.roles.vet'),
            'viewer' => __('admin/membership.roles.viewer'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('role')
                ->label(__('app/team.form.label.role'))
                ->options(self::roleOptions())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('app/team.table.column.email'))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('app/team.table.column.name'))
                    ->searchable()->toggleable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label(__('app/team.table.column.role'))
                    ->formatStateUsing(fn (string $state) => self::roleOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('joined_at')
                    ->label(__('app/team.table.column.joined_at'))
                    ->date()->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->label(__('app/team.table.column.revoked_at'))
                    ->date()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label(__('admin/membership.table.filter.status_label'))
                    ->placeholder(__('admin/membership.table.filter.status_placeholder'))
                    ->trueLabel(__('admin/membership.table.filter.status_true'))
                    ->falseLabel(__('admin/membership.table.filter.status_false'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('revoked_at'),
                        false: fn (Builder $query) => $query->whereNull('revoked_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add')
                    ->label(__('app/team.action.add.label'))
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label(__('admin/membership.form.label.attach_email'))
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin/membership.form.label.attach_name')),
                        Forms\Components\Select::make('role')
                            ->label(__('admin/membership.form.label.attach_role'))
                            ->options(self::roleOptions())
                            ->default('employee')
                            ->required(),
                    ])
                    ->action(function (array $data, AttachOrInviteUser $attach) {
                        $tenant = app(TenantManager::class)->tenantOrFail();

                        $result = $attach->execute([
                            'tenant_id' => $tenant->id,
                            'email' => $data['email'],
                            'name' => $data['name'] ?? null,
                            'role' => $data['role'],
                        ]);

                        if ($result['mode'] === 'attached') {
                            Notification::make()
                                ->success()
                                ->title(__('admin/membership.action.attach.success_attached_title'))
                                ->body(__('admin/membership.action.attach.success_attached_body', ['email' => $result['user']->email]))
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('admin/membership.action.attach.success_invited_title'))
                            ->body(__('admin/membership.action.attach.success_invited_body', [
                                'email' => $result['invitation']->email,
                                'expires' => $result['invitation']->expires_at->format('Y-m-d H:i'),
                            ]))
                            ->persistent()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data) => ['role' => $data['role'] ?? null]),
                Tables\Actions\Action::make('send_password_reset')
                    ->label(__('app/team.action.send_password_reset.label'))
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at === null && $r->user?->email)
                    ->requiresConfirmation()
                    ->modalDescription(fn (TenantMembership $r) => __('app/team.action.send_password_reset.modal_description', ['email' => $r->user?->email ?? '']))
                    ->action(function (TenantMembership $record) {
                        $email = $record->user?->email;
                        if (! $email) {
                            Notification::make()->danger()
                                ->title(__('app/team.action.send_password_reset.failure_no_email'))
                                ->send();

                            return;
                        }

                        $status = Password::broker('users')
                            ->sendResetLink(['email' => $email]);

                        if ($status === Password::RESET_LINK_SENT) {
                            Notification::make()->success()
                                ->title(__('app/team.action.send_password_reset.success_title'))
                                ->body(__('app/team.action.send_password_reset.success_body', ['email' => $email]))
                                ->send();
                        } else {
                            Notification::make()->danger()
                                ->title(__('app/team.action.send_password_reset.failure_title'))
                                ->body(__($status))
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label(__('admin/membership.action.revoke.label'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at === null)
                    ->requiresConfirmation()
                    ->action(function (TenantMembership $record, RevokeMembership $revoke) {
                        $revoke->execute($record);
                        Notification::make()->success()
                            ->title(__('admin/membership.action.revoke.success'))
                            ->send();
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label(__('admin/membership.action.reactivate.label'))
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at !== null)
                    ->requiresConfirmation()
                    ->action(function (TenantMembership $record, RevokeMembership $revoke) {
                        $revoke->reactivate($record);
                        Notification::make()->success()
                            ->title(__('admin/membership.action.reactivate.success'))
                            ->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = app(TenantManager::class)->current();

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id ?? '')
            ->with('user:id,name,email');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamMembers::route('/'),
        ];
    }
}
