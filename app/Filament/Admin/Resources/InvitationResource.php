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

    protected static ?string $navigationLabel = 'Zaproszenia';

    protected static ?string $navigationGroup = 'Stajnie';

    protected static ?string $modelLabel = 'zaproszenie';

    protected static ?string $pluralModelLabel = 'Zaproszenia';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->label('Stajnia')->searchable()->sortable(),
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
                Tables\Columns\TextColumn::make('invitedBy.email')->label('Zapraszający')->placeholder('—')->toggleable(),
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
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Stajnia')
                    ->relationship('tenant', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('Wyślij ponownie')
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
                            ->title('Zaproszenie wysłane ponownie')
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

                        Notification::make()->success()->title('Zaproszenie unieważnione')->send();
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
