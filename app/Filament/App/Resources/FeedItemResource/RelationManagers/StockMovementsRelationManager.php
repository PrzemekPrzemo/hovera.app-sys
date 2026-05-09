<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\FeedItemResource\RelationManagers;

use App\Models\Tenant\FeedStockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock movement history per feed item — read-only audit log.
 * New movements are added via the "Add movement" action on the
 * FeedItem table; freezing the timeline avoids tampering with history.
 */
class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app/feed_inventory.movements.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label(__('app/feed_inventory.movements.col_date'))
                    ->date('Y-m-d'),
                Tables\Columns\BadgeColumn::make('kind')
                    ->label(__('app/feed_inventory.movements.col_kind'))
                    ->formatStateUsing(fn (string $state) => __('app/feed_inventory.kind.'.$state))
                    ->colors([
                        'success' => 'purchase',
                        'primary' => 'adjustment',
                        'warning' => 'consumption',
                        'danger' => 'waste',
                    ]),
                Tables\Columns\TextColumn::make('delta')
                    ->label(__('app/feed_inventory.movements.col_amount'))
                    ->formatStateUsing(function ($state, FeedStockMovement $r): string {
                        $sign = (float) $state > 0 ? '+' : '';

                        return $sign.number_format((float) $state, 2, ',', ' ').' '.($r->feedItem->unit ?? '');
                    })
                    ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('app/feed_inventory.movements.col_notes'))
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->defaultSort('movement_date', 'desc');
    }
}
