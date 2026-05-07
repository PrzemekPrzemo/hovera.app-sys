<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\BoxResource\Pages;
use App\Models\Tenant\Box;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoxResource extends Resource
{
    protected static ?string $model = Box::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?string $navigationLabel = 'Boksy';

    protected static ?string $modelLabel = 'box';

    protected static ?string $pluralModelLabel = 'Boksy';

    protected static ?int $navigationSort = 35;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Box')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nazwa / numer')->required()->maxLength(60),
                    Forms\Components\TextInput::make('label')->label('Krótki kod (np. "12")')->maxLength(20),
                    Forms\Components\Select::make('type')
                        ->label('Typ')
                        ->options([
                            'indoor' => 'Box wewnętrzny',
                            'paddock' => 'Padok',
                            'outdoor' => 'Box zewnętrzny',
                            'quarantine' => 'Kwarantanna',
                        ])
                        ->default('indoor')
                        ->required(),
                    Forms\Components\TextInput::make('size_m2')
                        ->label('Rozmiar (m²)')
                        ->numeric()->minValue(1)->maxValue(500),
                    Forms\Components\TextInput::make('capacity')
                        ->label('Pojemność')
                        ->helperText('Ile koni może być w tym boksie (zwykle 1; większe boksy grupowe mogą mieć więcej).')
                        ->numeric()->minValue(1)->maxValue(20)->default(1)->required(),
                    Forms\Components\TextInput::make('monthly_rate_cents')
                        ->label('Miesięczna cena pensjonatu (gr)')
                        ->helperText('Domyślna stawka — można jeszcze override per koń lub klient.')
                        ->numeric()->minValue(0),
                    Forms\Components\Toggle::make('is_active')->label('Aktywny')->default(true),
                    Forms\Components\TextInput::make('sort_order')->label('Kolejność')->numeric()->default(0),
                ]),
            Forms\Components\Section::make('Notatki')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')->label('Notatki')->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable()->weight('bold'),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'indoor' => 'Wewnętrzny',
                        'paddock' => 'Padok',
                        'outdoor' => 'Zewnętrzny',
                        'quarantine' => 'Kwarantanna',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'indoor',
                        'success' => 'paddock',
                        'gray' => 'outdoor',
                        'warning' => 'quarantine',
                    ]),
                Tables\Columns\TextColumn::make('size_m2')->label('m²')->placeholder('—')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('horses_count')
                    ->label('Konie')
                    ->counts('horses')
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity')->label('Poj.')->sortable(),
                Tables\Columns\TextColumn::make('monthly_rate_cents')
                    ->label('Pensjonat')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywny')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'indoor' => 'Wewnętrzny',
                    'paddock' => 'Padok',
                    'outdoor' => 'Zewnętrzny',
                    'quarantine' => 'Kwarantanna',
                ]),
                Tables\Filters\Filter::make('vacant')
                    ->label('Wolne (≥1 miejsce)')
                    ->query(fn (Builder $q) => $q->whereDoesntHave('horses')),
                Tables\Filters\Filter::make('only_active')
                    ->label('Tylko aktywne')
                    ->query(fn (Builder $q) => $q->where('is_active', true))
                    ->default(),
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
            'index' => Pages\ListBoxes::route('/'),
            'create' => Pages\CreateBox::route('/create'),
            'edit' => Pages\EditBox::route('/{record}/edit'),
        ];
    }
}
