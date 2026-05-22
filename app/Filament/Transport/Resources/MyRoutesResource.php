<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\MyRoutesResource\Pages;
use App\Models\Tenant\Quote;
use App\Services\Tenancy\CurrentDriverResolver;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Driver-only widok: TYLKO trasy przypisane do zalogowanego kierowcy.
 * Wpis Quote'a (status = accepted) gdzie `driver_id` matches Driver
 * record bound do current user przez `central_user_id`.
 *
 * Read-only (canCreate/Edit/Delete → false) — kierowca nie modyfikuje
 * cudzych ofert; tylko ogląda swoje trasy i adresy do realizacji.
 *
 * Visible w sidebar tylko dla user'a który ma membership o roli
 * `driver` AND linked Driver record w tenant DB. Brak Driver → strona
 * nie pojawi się w nav (nadal accessible URL-em, ale empty list).
 */
class MyRoutesResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('transport/my_routes.navigation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport/my_routes.model_plural');
    }

    public static function getModelLabel(): string
    {
        return __('transport/my_routes.model');
    }

    public static function canAccess(): bool
    {
        // Tylko driver z linked Driver record. Inny role (operator/admin/
        // owner) widzi QuoteResource z pelnym dostepem.
        return app(CurrentDriverResolver::class)->current() !== null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $driver = app(CurrentDriverResolver::class)->current();
        $driverId = $driver?->id;

        // Scope do quote'ow przypisanych do tego kierowcy; pusty wynik
        // gdy brak Driver record (defensive, nie wybuchaj).
        return parent::getEloquentQuery()
            ->where('driver_id', $driverId ?? '_no_driver_')
            ->whereIn('status', [QuoteStatus::Accepted->value, QuoteStatus::Sent->value])
            ->orderBy('preferred_date');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('preferred_date')
                    ->label(__('transport/my_routes.column.date'))
                    ->date('Y-m-d, D')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pickup_address')
                    ->label(__('transport/my_routes.column.pickup'))
                    ->wrap()
                    ->limit(60),
                Tables\Columns\TextColumn::make('dropoff_address')
                    ->label(__('transport/my_routes.column.dropoff'))
                    ->wrap()
                    ->limit(60),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('transport/my_routes.column.customer'))
                    ->limit(30),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label(__('transport/my_routes.column.phone'))
                    ->url(fn ($state) => $state ? 'tel:'.$state : null),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('transport/my_routes.column.status'))
                    ->badge(),
            ])
            ->defaultSort('preferred_date', 'asc')
            ->emptyStateHeading(__('transport/my_routes.empty.heading'))
            ->emptyStateDescription(__('transport/my_routes.empty.description'))
            ->emptyStateIcon('heroicon-o-map');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyRoutes::route('/'),
        ];
    }
}
