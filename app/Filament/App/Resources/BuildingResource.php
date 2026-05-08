<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\BuildingResource\Pages;
use App\Models\Tenant\Building;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.buildings');
    }

    public static function getModelLabel(): string
    {
        return __('models.building');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.buildings');
    }

    protected static ?int $navigationSort = 34;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/building.form.section.building'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/building.form.label.name'))
                        ->placeholder(__('app/building.form.label.name_placeholder'))
                        ->required()->maxLength(120),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/building.form.label.sort_order'))
                        ->numeric()->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/building.form.label.is_active'))
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/building.form.label.notes'))
                        ->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/building.table.column.name'))
                    ->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('boxes_count')
                    ->label(__('app/building.table.column.boxes_count'))
                    ->counts('boxes')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('app/building.table.column.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
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
            'index' => Pages\ListBuildings::route('/'),
            'create' => Pages\CreateBuilding::route('/create'),
            'edit' => Pages\EditBuilding::route('/{record}/edit'),
        ];
    }
}
