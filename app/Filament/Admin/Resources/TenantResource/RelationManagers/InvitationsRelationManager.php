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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.invitations');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.invitation');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.invitations');
    }

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
                Tables\Actions\Action::make('show_url')
                    ->label('Pokaż link logowania')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (UserInvitation $r) => ! $r->isAccepted())
                    ->modalHeading(fn (UserInvitation $r) => "Link logowania dla {$r->email}")
                    ->modalDescription('Każde wywołanie generuje NOWY token (poprzedni jest unieważniany). Token surowy nie jest zapisany w DB — pojawia się tylko tutaj raz.')
                    ->action(function (UserInvitation $record, SendInvitation $send, MasterAuditLogger $audit) {
                        $result = $send->execute(
                            email: $record->email,
                            tenant: $record->tenant,
                            role: $record->role,
                            name: $record->name,
                            invitedBy: Auth::user(),
                        );

                        $url = route('invitations.accept', ['token' => $result['plaintext_token']]);

                        $audit->record(
                            'invitation.url_shown',
                            'UserInvitation',
                            $result['invitation']->id,
                            $record->tenant_id,
                            ['email' => $record->email],
                        );

                        Notification::make()
                            ->success()
                            ->title('Link wygenerowany — skopiuj poniżej:')
                            ->body($url)
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\Action::make('resend')
                    ->label('Wyślij mailem')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
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

                        $url = route('invitations.accept', ['token' => $result['plaintext_token']]);

                        $audit->record(
                            'invitation.resent',
                            'UserInvitation',
                            $result['invitation']->id,
                            $record->tenant_id,
                            ['email' => $record->email, 'previous_id' => $record->id],
                        );

                        Notification::make()
                            ->success()
                            ->title("Zaproszenie wysłane na {$record->email}")
                            ->body("Link (do skopiowania jeśli mail nie dojdzie):\n{$url}")
                            ->persistent()
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
