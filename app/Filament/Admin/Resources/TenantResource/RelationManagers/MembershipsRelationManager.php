<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Memberships\AttachOrInviteUser;
use App\Actions\Memberships\RevokeMembership;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Services\MasterAuditLogger;
use App\Support\ImpersonationDebug;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Członkostwa';

    protected static ?string $modelLabel = 'członek';

    protected static ?string $pluralModelLabel = 'Członkowie';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->label('Email użytkownika')
                ->email()
                ->required()
                ->disabledOn('edit')
                ->helperText('Jeśli użytkownik nie istnieje, zostanie utworzony i otrzyma wygenerowane hasło.'),

            Forms\Components\TextInput::make('name')
                ->label('Imię i nazwisko (opcjonalne, tylko przy nowym użytkowniku)')
                ->disabledOn('edit'),

            Forms\Components\Select::make('role')
                ->label('Rola w stajni')
                ->options(self::roleOptions())
                ->default('viewer')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Imię')->searchable()->toggleable(),
                Tables\Columns\BadgeColumn::make('role')->label('Rola')
                    ->formatStateUsing(fn (string $state) => self::roleOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('joined_at')->label('Dołączył')->date()->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')->label('Cofnięto')->date()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label('Status')
                    ->placeholder('Aktywne i cofnięte')
                    ->trueLabel('Tylko cofnięte')
                    ->falseLabel('Tylko aktywne')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('revoked_at'),
                        false: fn ($query) => $query->whereNull('revoked_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('attach')
                    ->label('Dodaj członka')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Imię i nazwisko (jeśli nowy użytkownik)'),
                        Forms\Components\Select::make('role')
                            ->label('Rola')
                            ->options(self::roleOptions())
                            ->default('viewer')
                            ->required(),
                    ])
                    ->action(function (array $data, AttachOrInviteUser $attach, MasterAuditLogger $audit) {
                        $tenant = $this->getOwnerRecord();

                        $result = $attach->execute([
                            'tenant_id' => $tenant->id,
                            'email' => $data['email'],
                            'name' => $data['name'] ?? null,
                            'role' => $data['role'],
                        ]);

                        if ($result['mode'] === 'attached') {
                            $audit->record(
                                'membership.attach',
                                'TenantMembership',
                                $result['membership']->id,
                                $tenant->id,
                                ['email' => $result['user']->email, 'role' => $data['role']],
                            );

                            Notification::make()
                                ->success()
                                ->title('Członek dodany')
                                ->body("Dodano {$result['user']->email} do stajni.")
                                ->send();

                            return;
                        }

                        // mode === 'invited'
                        $audit->record(
                            'invitation.sent',
                            'UserInvitation',
                            $result['invitation']->id,
                            $tenant->id,
                            ['email' => $result['invitation']->email, 'role' => $data['role']],
                        );

                        Notification::make()
                            ->success()
                            ->title('Zaproszenie wysłane')
                            ->body("Wysłano zaproszenie do {$result['invitation']->email}. Link wygasa "
                                .$result['invitation']->expires_at->format('Y-m-d H:i').'.')
                            ->persistent()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, TenantMembership $record) => array_merge($data, [
                        'email' => $record->user?->email,
                    ]))
                    ->after(function (TenantMembership $record, array $data, MasterAuditLogger $audit) {
                        $audit->record('membership.update_role', 'TenantMembership', $record->id, $record->tenant_id, [
                            'role' => $data['role'] ?? null,
                        ]);
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label('Cofnij dostęp')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at === null)
                    ->requiresConfirmation()
                    ->action(function (TenantMembership $record, RevokeMembership $revoke, MasterAuditLogger $audit) {
                        $revoke->execute($record);
                        $audit->record('membership.revoke', 'TenantMembership', $record->id, $record->tenant_id);
                        Notification::make()->success()->title('Dostęp cofnięty')->send();
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Przywróć')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at !== null)
                    ->requiresConfirmation()
                    ->action(function (TenantMembership $record, RevokeMembership $revoke, MasterAuditLogger $audit) {
                        $revoke->reactivate($record);
                        $audit->record('membership.reactivate', 'TenantMembership', $record->id, $record->tenant_id);
                        Notification::make()->success()->title('Dostęp przywrócony')->send();
                    }),
                Tables\Actions\Action::make('impersonate')
                    ->label('Zaloguj jako')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at === null && $r->user !== null)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Powód impersonacji (audit RODO)')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->helperText('Pole wymagane. Każda akcja w trakcie sesji impersonacji jest tagowana w audit_log stajni.'),
                    ])
                    ->action(function (TenantMembership $record, array $data, StartImpersonation $impersonate) {
                        /** @var Tenant $tenant */
                        $tenant = $record->tenant()->firstOrFail();
                        $target = $record->user()->firstOrFail();

                        ImpersonationDebug::snap('1_membership_action_before_execute', [
                            'tenant_id' => $tenant->id,
                            'target_user_id' => $target->id,
                        ]);

                        $impersonate->execute(
                            masterAdmin: Auth::user(),
                            tenant: $tenant,
                            targetUser: $target,
                            reason: (string) $data['reason'],
                            session: request()->session(),
                        );

                        ImpersonationDebug::snap('1_membership_action_after_execute');
                    })
                    ->successRedirectUrl('/app')
                    ->modalSubmitActionLabel('Rozpocznij impersonację'),
            ]);
    }

    /**
     * @return array<string,string>
     */
    public static function roleOptions(): array
    {
        return [
            'owner' => 'Właściciel',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'instructor' => 'Instruktor',
            'employee' => 'Pracownik',
            'vet' => 'Weterynarz',
            'viewer' => 'Tylko podgląd',
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Only role is editable on existing memberships; email and name
        // are intentionally locked.
        return ['role' => $data['role']];
    }
}
