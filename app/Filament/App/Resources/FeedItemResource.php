<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\FeedItemResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\FeedItem;
use App\Models\Tenant\FeedStockMovement;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FeedItemResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FEED_STAFF;
    }

    protected static ?string $model = FeedItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 39;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.feed_inventory');
    }

    public static function getModelLabel(): string
    {
        return __('models.feed_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.feed_items');
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $low = FeedItem::query()->active()->get()->filter->isLowStock()->count();

            return $low > 0 ? (string) $low : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/feed_inventory.form.section.item'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/feed_inventory.form.label.name'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('unit')
                        ->label(__('app/feed_inventory.form.label.unit'))
                        ->default('kg')
                        ->required()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('low_stock_threshold')
                        ->label(__('app/feed_inventory.form.label.low_stock_threshold'))
                        ->helperText(__('app/feed_inventory.form.helper.low_stock_threshold'))
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/feed_inventory.form.label.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/feed_inventory.form.label.is_active'))
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/feed_inventory.form.label.notes'))
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/feed_inventory.table.column.name'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label(__('app/feed_inventory.table.column.current_stock'))
                    ->state(fn (FeedItem $r) => self::formatAmount($r->currentStock()).' '.$r->unit)
                    ->badge()
                    ->color(fn (FeedItem $r): string => $r->isLowStock() ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label(__('app/feed_inventory.table.column.low_stock_threshold'))
                    ->formatStateUsing(fn (?string $state, FeedItem $r) => $state !== null
                        ? self::formatAmount((float) $state).' '.$r->unit
                        : '—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('app/feed_inventory.table.column.is_active')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('app/feed_inventory.table.column.updated_at'))
                    ->since()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\Filter::make('low_stock')
                    ->label(__('app/feed_inventory.table.filter.low_stock'))
                    ->query(fn ($query) => $query->whereNotNull('low_stock_threshold')),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\Action::make('add_movement')
                    ->label(__('app/feed_inventory.actions.add_movement'))
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->form([
                        Forms\Components\Select::make('kind')
                            ->label(__('app/feed_inventory.form.label.kind'))
                            ->options([
                                'purchase' => __('app/feed_inventory.kind.purchase'),
                                'consumption' => __('app/feed_inventory.kind.consumption'),
                                'adjustment' => __('app/feed_inventory.kind.adjustment'),
                                'waste' => __('app/feed_inventory.kind.waste'),
                            ])
                            ->required()
                            ->default('purchase'),
                        Forms\Components\TextInput::make('amount')
                            ->label(__('app/feed_inventory.form.label.amount'))
                            ->helperText(__('app/feed_inventory.form.helper.amount'))
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                        Forms\Components\DatePicker::make('movement_date')
                            ->label(__('app/feed_inventory.form.label.movement_date'))
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label(__('app/feed_inventory.form.label.movement_notes'))
                            ->rows(2),
                    ])
                    ->action(function (FeedItem $record, array $data): void {
                        $amount = (float) $data['amount'];
                        // Sign convention: purchase + adjustment positive (add to stock),
                        // consumption + waste negative (remove from stock).
                        $delta = match ($data['kind']) {
                            'consumption', 'waste' => -abs($amount),
                            default => abs($amount),
                        };

                        FeedStockMovement::create([
                            'id' => (string) Str::ulid(),
                            'feed_item_id' => $record->id,
                            'delta' => $delta,
                            'kind' => $data['kind'],
                            'movement_date' => $data['movement_date'],
                            'notes' => $data['notes'] ?? null,
                            'user_central_id' => auth()->id(),
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedItems::route('/'),
            'create' => Pages\CreateFeedItem::route('/create'),
            'edit' => Pages\EditFeedItem::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            FeedItemResource\RelationManagers\StockMovementsRelationManager::class,
        ];
    }

    private static function formatAmount(float $value): string
    {
        // Strip trailing zeros: 12.50 → "12,5"
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }
}
