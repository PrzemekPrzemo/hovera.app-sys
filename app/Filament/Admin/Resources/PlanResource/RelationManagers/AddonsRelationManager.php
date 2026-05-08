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
                    ->helperText('Identyfikator (unikalny w obrębie planu), np. horses_plus_10.'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Etykieta marketingowa, np. "+10 koni".'),
            ]),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('resource_type')
                    ->label('Typ zasobu')
                    ->options([
                        'horses' => 'Konie',
                        'users' => 'Użytkownicy',
                        'clients' => 'Klienci',
                        'storage_gb' => 'Storage (GB)',
                        'custom' => 'Inne',
                    ])
                    ->default('horses')
                    ->required()
                    ->helperText('Rodzaj limitu/zasobu który dodatek zwiększa.'),
                Forms\Components\TextInput::make('quantity')
                    ->label('Ilość')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(10)
                    ->helperText('O ile zwiększa limit (np. 10 dla "+10 koni").'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                PriceInput::make('price_monthly_cents', 'Cena miesięczna'),
                PriceInput::make('price_yearly_cents', 'Cena roczna'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktywny')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->helperText('Niższe = wyżej na liście.'),
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
                    ->label('Zasób')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'horses' => 'Konie',
                        'users' => 'Użytkownicy',
                        'clients' => 'Klienci',
                        'storage_gb' => 'GB',
                        'custom' => 'Inne',
                        default => '—',
                    }),
                Tables\Columns\TextColumn::make('quantity')->label('Ilość')->numeric(),
                Tables\Columns\TextColumn::make('price_monthly_cents')
                    ->label('Mies.')
                    ->formatStateUsing(fn (?int $state, PlanAddon $record): string => $state === null
                        ? '—'
                        : number_format($state / 100, 2, ',', ' ').' '.$record->plan->currency),
                Tables\Columns\TextColumn::make('price_yearly_cents')
                    ->label('Rocznie')
                    ->formatStateUsing(fn (?int $state, PlanAddon $record): string => $state === null
                        ? '—'
                        : number_format($state / 100, 2, ',', ' ').' '.$record->plan->currency),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Akt.'),
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
