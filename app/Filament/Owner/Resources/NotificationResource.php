<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\NotificationResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * PR O1 — Owner notifications hub. Filament Resource oparty o native
 * Laravel `notifications` table. Owner po loginie widzi listę swoich
 * notyfikacji (faktury wystawione, wizyty weterynaryjne, nowe wiadomości,
 * akceptacje boarding'u) z badge'em unread count w nawigacji.
 *
 * Existing dispatch pipeline (PR 6.1 / OwnerNotificationDispatcher):
 *   - NewInvoiceForOwner (database + mail)
 *   - VetVisitRecordedForOwner (database + mail)
 *   - NewMessageForOwner (database)
 *   - QuoteSentForOwnerNotification (database + mail)
 *
 * Akcje row-level:
 *   - "Otwórz" — mark-as-read + redirect na data.url (jeśli notification
 *     ma url w payload)
 *   - "Oznacz jako przeczytane" — silent mark-read
 *
 * Bulk: "Oznacz wszystkie jako przeczytane"
 *
 * Per-row notification storage: Laravel `notifications` table — kolumny
 * `data` (JSON), `read_at`, `created_at`. Każda Notification klasa serializuje
 * via `toDatabase()` własną reprezentację — tu odczytujemy `data.title`,
 * `data.body`, `data.url` (konwencja przyjmowana w istniejących klasach).
 */
class NotificationResource extends Resource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    public static function getNavigationLabel(): string
    {
        return __('owner/notification.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public static function getModelLabel(): string
    {
        return __('owner/notification.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/notification.model.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $count = $user->unreadNotifications()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        // Defensive — gdy user nie zalogowany (np. session expired), zwracamy
        // pustą query zamiast crash'a. Filament middleware i tak by przekierował.
        if (! $user) {
            return DatabaseNotification::query()->whereRaw('0 = 1');
        }

        return DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->getKey());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('read_at')
                    ->label(__('owner/notification.column.read'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-circle')
                    ->trueColor('gray')
                    ->falseColor('warning')
                    ->getStateUsing(fn (DatabaseNotification $r) => $r->read_at !== null),
                Tables\Columns\TextColumn::make('data.title')
                    ->label(__('owner/notification.column.title'))
                    ->searchable()
                    ->weight(fn (DatabaseNotification $r) => $r->read_at === null ? 'bold' : null)
                    ->getStateUsing(fn (DatabaseNotification $r) => (string) (data_get($r->data, 'title') ?? data_get($r->data, 'subject') ?? __('owner/notification.fallback_title'))),
                Tables\Columns\TextColumn::make('data.body')
                    ->label(__('owner/notification.column.body'))
                    ->wrap()
                    ->limit(160)
                    ->getStateUsing(fn (DatabaseNotification $r) => (string) (data_get($r->data, 'body') ?? data_get($r->data, 'message') ?? '')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('owner/notification.column.received_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('unread_only')
                    ->label(__('owner/notification.filter.unread'))
                    ->query(fn (Builder $q) => $q->whereNull('read_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label(__('owner/notification.action.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->visible(fn (DatabaseNotification $r) => data_get($r->data, 'url') !== null)
                    ->url(fn (DatabaseNotification $r) => (string) data_get($r->data, 'url'))
                    ->openUrlInNewTab(false)
                    ->after(function (DatabaseNotification $r) {
                        if ($r->read_at === null) {
                            $r->markAsRead();
                        }
                    }),
                Tables\Actions\Action::make('mark_read')
                    ->label(__('owner/notification.action.mark_read'))
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->visible(fn (DatabaseNotification $r) => $r->read_at === null)
                    ->action(fn (DatabaseNotification $r) => $r->markAsRead()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_all_read')
                        ->label(__('owner/notification.bulk.mark_all_read'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false; // notifications są dispatched programowo, nie tworzy ich user
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
        ];
    }
}
