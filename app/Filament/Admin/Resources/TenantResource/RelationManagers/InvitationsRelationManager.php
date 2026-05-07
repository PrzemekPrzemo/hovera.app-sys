<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Actions\Invitations\SendInvitation;
use App\Models\Central\UserInvitation;
use App\Services\MasterAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = 'Zaproszenia';

    protected static ?string $modelLabel = 'zaproszenie';

    protected static ?string $pluralModelLabel = 'Zaproszenia';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('role')->label('Rola')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (UserInvitation $r) => $r->status())
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'accepted',
                        'gray' => 'expired',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Oczekuje',
                        'accepted' => 'Zaakceptowane',
                        'expired' => 'Wygasłe',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('invited_by_user_id')
                    ->label('Zapraszający')
                    ->getStateUsing(fn (UserInvitation $r) => $r->invitedBy?->email ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->label('Wygasa')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('accepted_at')->label('Zaakceptowane')->dateTime()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Wysłane')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('only_pending')
                    ->label('Tylko oczekujące')
                    ->query(fn ($query) => $query->pending())
                    ->default(),
                Tables\Filters\Filter::make('expired')
                    ->label('Tylko wygasłe')
                    ->query(fn ($query) => $query->expired()),
                Tables\Filters\Filter::make('accepted')
                    ->label('Tylko zaakceptowane')
                    ->query(fn ($query) => $query->accepted()),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('Wyślij ponownie')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
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
                            ->title('Zaproszenie wysłane ponownie')
                            ->body("Nowy link wysłany na {$record->email}. Poprzedni unieważniony.")
                            ->send();
                    }),

                Tables\Actions\Action::make('revoke')
                    ->label('Unieważnij')
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

                        Notification::make()
                            ->success()
                            ->title('Zaproszenie unieważnione')
                            ->send();
                    }),
            ]);
    }
}
