<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlanResource\RelationManagers;

use App\Filament\Components\PriceInput;
use App\Models\Central\PlanAddon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AddonsRelationManager extends RelationManager
{
    protected static string $relationship = 'addons';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.addons');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.addon');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.addons');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->alphaDash()
                    ->maxLength(64)
                    ->helperText(__('admin/addon.form.helper.code')),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->helperText(__('admin/addon.form.helper.name')),
            ]),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('resource_type')
                    ->label(__('admin/addon.form.label.resource_type'))
                    ->options([
                        'horses' => __('admin/addon.form.resource_types.horses'),
                        'users' => __('admin/addon.form.resource_types.users'),
                        'clients' => __('admin/addon.form.resource_types.clients'),
                        'storage_gb' => __('admin/addon.form.resource_types.storage_gb'),
                        'custom' => __('admin/addon.form.resource_types.custom'),
                    ])
                    ->default('horses')
                    ->required()
                    ->helperText(__('admin/addon.form.helper.resource_type')),
                Forms\Components\TextInput::make('quantity')
                    ->label(__('admin/addon.form.label.quantity'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(10)
                    ->helperText(__('admin/addon.form.helper.quantity')),
            ]),
            Forms\Components\Grid::make(2)->schema([
                PriceInput::make('price_monthly_cents', __('admin/addon.form.label.price_monthly')),
                PriceInput::make('price_yearly_cents', __('admin/addon.form.label.price_yearly')),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label(__('admin/addon.form.label.is_active'))
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->helperText(__('admin/addon.form.helper.sort_order')),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('resource_type')
                    ->label(__('admin/addon.table.column.resource_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'horses' => __('admin/addon.table.resource_types_short.horses'),
                        'users' => __('admin/addon.table.resource_types_short.users'),
                        'clients' => __('admin/addon.table.resource_types_short.clients'),
                        'storage_gb' => __('admin/addon.table.resource_types_short.storage_gb'),
                        'custom' => __('admin/addon.table.resource_types_short.custom'),
                        default => '—',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('admin/addon.table.column.quantity'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('price_monthly_cents')
                    ->label(__('admin/addon.table.column.price_monthly_short'))
                    ->formatStateUsing(fn (?int $state, PlanAddon $record): string => $state === null
                        ? '—'
                        : number_format($state / 100, 2, ',', ' ').' '.$record->plan->currency),
                Tables\Columns\TextColumn::make('price_yearly_cents')
                    ->label(__('admin/addon.table.column.price_yearly'))
                    ->formatStateUsing(fn (?int $state, PlanAddon $record): string => $state === null
                        ? '—'
                        : number_format($state / 100, 2, ',', ' ').' '.$record->plan->currency),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('admin/addon.table.column.is_active_short')),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
