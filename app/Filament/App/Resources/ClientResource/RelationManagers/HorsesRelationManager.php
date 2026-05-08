<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ClientResource\RelationManagers;

use App\Filament\App\Resources\HorseResource;
use App\Models\Tenant\Horse;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Tab "Konie" w karcie klienta — read-only widok koni których
 * klient jest właścicielem (Horse.owner_client_id). Umożliwia
 * szybki przegląd portfolio właściciela bez przechodzenia do
 * /app/horses i filtrowania.
 */
class HorsesRelationManager extends RelationManager
{
    protected static string $relationship = 'horses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.horses');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.horse');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.horses');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/horse.table.column.name'))
                    ->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('breed')
                    ->label(__('app/horse.table.column.breed'))
                    ->placeholder('—')->toggleable(),
                Tables\Columns\BadgeColumn::make('sex')
                    ->label(__('app/horse.table.column.sex'))
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (HorseResource::sexOptions()[$state] ?? $state)),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label(__('app/horse.table.column.birth_date'))
                    ->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('box.name')
                    ->label(__('app/horse.form.label.box'))
                    ->placeholder(__('app/horse.form.label.box_placeholder'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('microchip')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('passport_number')
                    ->label(__('app/horse.form.label.passport_number'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label(__('common.actions.view'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Horse $record) => HorseResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
