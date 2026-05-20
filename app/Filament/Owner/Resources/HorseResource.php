<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\HorseResource\Pages;
use App\Models\Tenant\OwnerHorse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Owner: "Moje konie". Lekki CRUD z minimum pól — owner nie zarządza
 * boksami, vetem, pensjonem (to stable). Trzyma tylko własną kartotekę
 * koni: imię, rasa, data ur., paszport, microchip, notatki.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md PR 6 §"Schema".
 */
class HorseResource extends Resource
{
    protected static ?string $model = OwnerHorse::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_horses');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/horses.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('owner/horses.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/horses.model.plural');
    }

    /** @return array<string,string> */
    public static function sexOptions(): array
    {
        return [
            'mare' => __('owner/horses.sex.mare'),
            'stallion' => __('owner/horses.sex.stallion'),
            'gelding' => __('owner/horses.sex.gelding'),
            'filly' => __('owner/horses.sex.filly'),
            'colt' => __('owner/horses.sex.colt'),
            'foal' => __('owner/horses.sex.foal'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('owner/horses.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('owner/horses.form.label.name'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('breed')
                        ->label(__('owner/horses.form.label.breed'))
                        ->maxLength(120),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label(__('owner/horses.form.label.birth_date'))
                        ->native(false)
                        ->maxDate(now()),
                    Forms\Components\Select::make('sex')
                        ->label(__('owner/horses.form.label.sex'))
                        ->options(self::sexOptions())
                        ->native(false),
                    Forms\Components\TextInput::make('color')
                        ->label(__('owner/horses.form.label.color'))
                        ->maxLength(60),
                    Forms\Components\TextInput::make('passport_number')
                        ->label(__('owner/horses.form.label.passport_number'))
                        ->maxLength(64),
                    Forms\Components\TextInput::make('microchip')
                        ->label(__('owner/horses.form.label.microchip'))
                        ->maxLength(32),
                ]),

            Forms\Components\Section::make(__('owner/horses.form.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('owner/horses.form.label.notes'))
                        ->rows(4)
                        ->maxLength(2000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('owner/horses.table.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label(__('owner/horses.table.breed'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label(__('owner/horses.table.birth_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('sex')
                    ->label(__('owner/horses.table.sex'))
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (self::sexOptions()[$state] ?? $state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('passport_number')
                    ->label(__('owner/horses.table.passport_number'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->emptyStateHeading(__('owner/horses.empty.heading'))
            ->emptyStateDescription(__('owner/horses.empty.description'))
            ->emptyStateIcon('heroicon-o-bolt');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorses::route('/'),
            'create' => Pages\CreateHorse::route('/create'),
            'edit' => Pages\EditHorse::route('/{record}/edit'),
        ];
    }
}
