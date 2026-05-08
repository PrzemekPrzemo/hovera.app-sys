<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\BoardingFrequency;
use App\Filament\App\Resources\BoardingServiceResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Tenant\BoardingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoardingServiceResource extends Resource
{
    protected static ?string $model = BoardingService::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.boarding_services');
    }

    public static function getModelLabel(): string
    {
        return __('models.boarding_service');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.boarding_services');
    }

    protected static ?int $navigationSort = 36;

    /** @return array<string,string> */
    private static function vatRateOptions(): array
    {
        return [
            '23' => __('app/boarding.vat_rates.23'),
            '8' => __('app/boarding.vat_rates.8'),
            '5' => __('app/boarding.vat_rates.5'),
            '0' => __('app/boarding.vat_rates.0'),
            'zw' => __('app/boarding.vat_rates.zw'),
            'np' => __('app/boarding.vat_rates.np'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/boarding.form.section.service'))
                ->description(__('app/boarding.form.section.service_description'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/boarding.form.label.name'))
                        ->placeholder(__('app/boarding.form.label.name_placeholder'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('description')
                        ->label(__('app/boarding.form.label.description'))->maxLength(500),
                    Forms\Components\TextInput::make('unit')
                        ->label(__('app/boarding.form.label.unit'))
                        ->placeholder(__('app/boarding.form.label.unit_placeholder'))
                        ->default('szt.')
                        ->required()
                        ->maxLength(32),
                    Forms\Components\Select::make('frequency')
                        ->label(__('app/boarding.form.label.frequency'))
                        ->options(BoardingFrequency::options())
                        ->required()
                        ->default('monthly'),
                    PriceInput::make('price_cents', __('app/boarding.form.label.price_net'))
                        ->required(),
                    Forms\Components\Select::make('vat_rate')
                        ->label(__('app/boarding.form.label.vat_rate'))
                        ->options(self::vatRateOptions())
                        ->default('23')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/boarding.form.label.is_active'))->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/boarding.form.label.sort_order'))->numeric()->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/boarding.table.column.name'))
                    ->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('frequency')
                    ->label(__('app/boarding.table.column.frequency'))
                    ->formatStateUsing(fn (BoardingFrequency $state) => $state->label())
                    ->badge(),
                Tables\Columns\TextColumn::make('price_cents')
                    ->label(__('app/boarding.table.column.price_net'))
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' zł')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_rate')
                    ->label(__('app/boarding.table.column.vat'))
                    ->formatStateUsing(fn (string $state) => is_numeric($state) ? $state.'%' : $state),
                Tables\Columns\TextColumn::make('horses_count')
                    ->label(__('app/boarding.table.column.horses_count'))
                    ->counts('horses')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('app/boarding.table.column.is_active')),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('frequency')->options(BoardingFrequency::options()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoardingServices::route('/'),
            'create' => Pages\CreateBoardingService::route('/create'),
            'edit' => Pages\EditBoardingService::route('/{record}/edit'),
        ];
    }
}
