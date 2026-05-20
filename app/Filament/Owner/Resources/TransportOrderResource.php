<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Enums\CalculationMode;
use App\Filament\Owner\Resources\TransportOrderResource\Pages;
use App\Models\Tenant\TransportOrder;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Owner: "Moje zamówienia transportu". Lista łączników do centralnych
 * TransportLead'ów. Read-only — owner tworzy zamówienia przez stronę
 * `/owner/order-transport` (mini-Calculator), tu tylko śledzi status
 * i klika View żeby zobaczyć szczegóły + responses.
 *
 * Akcja "Cancel" przyjdzie w kolejnym PR razem z lead lifecycle'em.
 */
class TransportOrderResource extends Resource
{
    protected static ?string $model = TransportOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_transport');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/transport.orders.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('owner/transport.orders.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/transport.orders.model.plural');
    }

    /** @return array<string,string> */
    public static function statusOptions(): array
    {
        return [
            'draft' => __('owner/transport.orders.status.draft'),
            'open' => __('owner/transport.orders.status.open'),
            'quoted' => __('owner/transport.orders.status.quoted'),
            'accepted' => __('owner/transport.orders.status.accepted'),
            'expired' => __('owner/transport.orders.status.expired'),
            'cancelled' => __('owner/transport.orders.status.cancelled'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('preferred_date')
                    ->label(__('owner/transport.orders.table.date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pickup_address')
                    ->label(__('owner/transport.orders.table.pickup'))
                    ->limit(40)
                    ->tooltip(fn (TransportOrder $r) => $r->pickup_address),
                Tables\Columns\TextColumn::make('dropoff_address')
                    ->label(__('owner/transport.orders.table.dropoff'))
                    ->limit(40)
                    ->tooltip(fn (TransportOrder $r) => $r->dropoff_address),
                Tables\Columns\TextColumn::make('horse.name')
                    ->label(__('owner/transport.orders.table.horse'))
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('calculation_mode')
                    ->label(__('owner/transport.orders.table.mode'))
                    ->formatStateUsing(fn (?string $state) => $state === null
                        ? '—'
                        : (CalculationMode::tryFrom($state)?->label() ?? $state))
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('owner/transport.orders.table.status'))
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (self::statusOptions()[$state] ?? $state))
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'open',
                        'info' => 'quoted',
                        'success' => 'accepted',
                        'danger' => fn ($state) => in_array($state, ['expired', 'cancelled'], true),
                    ]),
            ])
            ->defaultSort('preferred_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->emptyStateHeading(__('owner/transport.orders.empty.heading'))
            ->emptyStateDescription(__('owner/transport.orders.empty.description'))
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('owner/transport.orders.empty.cta'))
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn () => route('filament.owner.pages.order-transport')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportOrders::route('/'),
            'view' => Pages\ViewTransportOrder::route('/{record}'),
        ];
    }
}
