<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Actions\Invitations\SendInvitation;
use App\Filament\Admin\Resources\InvitationResource\Pages;
use App\Models\Central\UserInvitation;
use App\Services\MasterAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InvitationResource extends Resource
{
    protected static ?string $model = UserInvitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('navigation.invitations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stables');
    }

    public static function getModelLabel(): string
    {
        return __('models.invitation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.invitations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('admin/invitation.table.column.tenant'))
                    ->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label(__('admin/invitation.table.column.role'))
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin/invitation.table.column.status'))
                    ->getStateUsing(fn (UserInvitation $r) => $r->status())
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'accepted',
                        'gray' => 'expired',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => __('admin/invitation.table.status.pending'),
                        'accepted' => __('admin/invitation.table.status.accepted'),
                        'expired' => __('admin/invitation.table.status.expired'),
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('invitedBy.email')
                    ->label(__('admin/invitation.table.column.invited_by'))
                    ->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('admin/invitation.table.column.expires_at'))
                    ->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('accepted_at')
                    ->label(__('admin/invitation.table.column.accepted_at'))
                    ->dateTime()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/invitation.table.column.created_at'))
                    ->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('only_pending')
                    ->label(__('admin/invitation.table.filter.only_pending'))
                    ->query(fn ($query) => $query->pending())
                    ->default(),
                Tables\Filters\Filter::make('expired')
                    ->label(__('admin/invitation.table.filter.expired'))
                    ->query(fn ($query) => $query->expired()),
                Tables\Filters\Filter::make('accepted')
                    ->label(__('admin/invitation.table.filter.accepted'))
                    ->query(fn ($query) => $query->accepted()),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('admin/invitation.table.filter.tenant'))
                    ->relationship('tenant', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label(__('admin/invitation.action.resend.label'))
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (UserInvitation $r) => ! $r->isAccepted())
                    ->requiresConfirmation()
                    ->action(function (UserInvitation $record, SendInvitation $send, MasterAuditLogger $audit) {
                        $result = $send->execute(
                            email: $record->email,
                            tenant: $record->tenant,
                            role: $record->role,
                            name: $record->name,
                            invitedBy: Auth::user(),
                        );

                        $audit->record(
                            'invitation.resent',
                            'UserInvitation',
                            $result['invitation']->id,
                            $record->tenant_id,
                            ['email' => $record->email, 'previous_id' => $record->id],
                        );

                        Notification::make()
                            ->success()
                            ->title(__('admin/invitation.action.resend.success'))
                            ->send();
                    }),

                Tables\Actions\Action::make('revoke')
                    ->label(__('admin/invitation.action.revoke.label'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (UserInvitation $r) => $r->isUsable())
                    ->requiresConfirmation()
                    ->action(function (UserInvitation $record, MasterAuditLogger $audit) {
                        $record->forceFill(['expires_at' => now()->subSecond()])->save();

                        $audit->record(
                            'invitation.revoked',
                            'UserInvitation',
                            $record->id,
                            $record->tenant_id,
                            ['email' => $record->email],
                        );

                        Notification::make()->success()
                            ->title(__('admin/invitation.action.revoke.success'))
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
        ];
    }
}
