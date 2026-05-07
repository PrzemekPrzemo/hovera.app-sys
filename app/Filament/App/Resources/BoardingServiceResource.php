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

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?string $navigationLabel = 'Cennik pensji';

    protected static ?string $modelLabel = 'usługa pensji';

    protected static ?string $pluralModelLabel = 'Cennik pensji';

    protected static ?int $navigationSort = 36;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Usługa w cenniku')
                ->description('Te usługi wybierasz przy każdym koniu (zakładka "Pensja" na karcie konia). Pojawią się w portalu klienta — właściciel widzi za co płaci.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa')
                        ->placeholder('np. Siano, Sprzątanie boksu, Transport na zawody')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('description')->label('Opis (opcjonalnie)')->maxLength(500),
                    Forms\Components\TextInput::make('unit')
                        ->label('Jednostka')
                        ->placeholder('szt. / kg / godz. / m-c')
                        ->default('szt.')
                        ->required()
                        ->maxLength(32),
                    Forms\Components\Select::make('frequency')
                        ->label('Częstotliwość naliczania')
                        ->options(BoardingFrequency::options())
                        ->required()
                        ->default('monthly'),
                    PriceInput::make('price_cents', 'Cena netto')
                        ->required(),
                    Forms\Components\Select::make('vat_rate')
                        ->label('Stawka VAT')
                        ->options([
                            '23' => '23%',
                            '8' => '8%',
                            '5' => '5%',
                            '0' => '0%',
                            'zw' => 'zw. (zwolniona)',
                            'np' => 'np. (nie podlega)',
                        ])
                        ->default('23')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')->label('Aktywna')->default(true),
                    Forms\Components\TextInput::make('sort_order')->label('Kolejność')->numeric()->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Częstotliwość')
                    ->formatStateUsing(fn (BoardingFrequency $state) => $state->label())
                    ->badge(),
                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Cena netto')
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' zł')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_rate')
                    ->label('VAT')
                    ->formatStateUsing(fn (string $s) => is_numeric($s) ? $s.'%' : $s),
                Tables\Columns\TextColumn::make('horses_count')
                    ->label('Konie')
                    ->counts('horses')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Aktywna'),
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
