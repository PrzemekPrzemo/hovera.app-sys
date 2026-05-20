<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Memberships\AttachOrInviteUser;
use App\Actions\Memberships\RevokeMembership;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.memberships');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.membership');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.memberships');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->label(__('admin/membership.form.label.email'))
                ->email()
                ->required()
                ->disabledOn('edit')
                ->helperText(__('admin/membership.form.helper.email')),

            Forms\Components\TextInput::make('name')
                ->label(__('admin/membership.form.label.name'))
                ->disabledOn('edit'),

            Forms\Components\Select::make('role')
                ->label(__('admin/membership.form.label.role'))
                ->options(fn () => self::roleOptions($this->getOwnerRecord()->type ?? null))
                ->default(fn () => ($this->getOwnerRecord()->type ?? null) === TenantType::Transporter ? 'driver' : 'viewer')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('admin/membership.table.column.email'))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('admin/membership.table.column.name'))
                    ->searchable()->toggleable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label(__('admin/membership.table.column.role'))
                    ->formatStateUsing(fn (string $state) => self::roleOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('joined_at')
                    ->label(__('admin/membership.table.column.joined_at'))
                    ->date()->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->label(__('admin/membership.table.column.revoked_at'))
                    ->date()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('revoked_at')
                    ->label(__('admin/membership.table.filter.status_label'))
                    ->placeholder(__('admin/membership.table.filter.status_placeholder'))
                    ->trueLabel(__('admin/membership.table.filter.status_true'))
                    ->falseLabel(__('admin/membership.table.filter.status_false'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('revoked_at'),
                        false: fn ($query) => $query->whereNull('revoked_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('attach')
                    ->label(__('admin/membership.action.attach.label'))
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
                            ->options(fn () => self::roleOptions($this->getOwnerRecord()->type ?? null))
                            ->default(fn () => ($this->getOwnerRecord()->type ?? null) === TenantType::Transporter ? 'driver' : 'viewer')
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
                                ->title(__('admin/membership.action.attach.success_attached_title'))
                                ->body(__('admin/membership.action.attach.success_attached_body', ['email' => $result['user']->email]))
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
                    ->mutateRecordDataUsing(fn (array $data, TenantMembership $record) => array_merge($data, [
                        'email' => $record->user?->email,
                    ]))
                    ->after(function (TenantMembership $record, array $data, MasterAuditLogger $audit) {
                        $audit->record('membership.update_role', 'TenantMembership', $record->id, $record->tenant_id, [
                            'role' => $data['role'] ?? null,
                        ]);
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label(__('admin/membership.action.revoke.label'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at === null)
                    ->requiresConfirmation()
                    ->action(function (TenantMembership $record, RevokeMembership $revoke, MasterAuditLogger $audit) {
                        $revoke->execute($record);
                        $audit->record('membership.revoke', 'TenantMembership', $record->id, $record->tenant_id);
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
                    ->action(function (TenantMembership $record, RevokeMembership $revoke, MasterAuditLogger $audit) {
                        $revoke->reactivate($record);
                        $audit->record('membership.reactivate', 'TenantMembership', $record->id, $record->tenant_id);
                        Notification::make()->success()
                            ->title(__('admin/membership.action.reactivate.success'))
                            ->send();
                    }),
                Tables\Actions\Action::make('impersonate')
                    ->label(__('admin/membership.action.impersonate.label'))
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn (TenantMembership $r) => $r->revoked_at === null && $r->user !== null)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('admin/membership.form.label.impersonate_reason'))
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->helperText(__('admin/membership.form.helper.impersonate_reason')),
                    ])
                    ->action(function (TenantMembership $record, array $data, StartImpersonation $impersonate) {
                        /** @var Tenant $tenant */
                        $tenant = $record->tenant()->firstOrFail();
                        $target = $record->user()->firstOrFail();

                        $impersonate->execute(
                            masterAdmin: Auth::user(),
                            tenant: $tenant,
                            targetUser: $target,
                            reason: (string) $data['reason'],
                            session: request()->session(),
                        );
                    })
                    ->successRedirectUrl('/app')
                    ->modalSubmitActionLabel(__('admin/membership.action.impersonate.submit')),
            ]);
    }

    /**
     * Role options dependent on tenant type. Stable tenants mają cały
     * stable-flavored set (instructor/employee/vet itp.), transporter
     * tenants mają węższy zestaw związany z business model'em
     * (operator zamiast manager, driver zamiast instructor).
     *
     * Wywołanie bez argumentu zwraca pełny zestaw dla legacy callers
     * (np. niefiltrowane filtry tabel).
     *
     * @return array<string,string>
     */
    public static function roleOptions(?TenantType $type = null): array
    {
        if ($type === TenantType::Transporter) {
            return [
                'owner' => __('admin/membership.roles.owner'),
                'admin' => __('admin/membership.roles.admin'),
                'operator' => __('admin/membership.roles.operator'),
                'driver' => __('admin/membership.roles.driver'),
            ];
        }

        // Default = stable roles (włącznie z niefiltrowanym przypadkiem $type=null
        // żeby istniejące filtry tabel master admina pokazywały wszystkie role).
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Only role is editable on existing memberships; email and name
        // are intentionally locked.
        return ['role' => $data['role']];
    }
}
