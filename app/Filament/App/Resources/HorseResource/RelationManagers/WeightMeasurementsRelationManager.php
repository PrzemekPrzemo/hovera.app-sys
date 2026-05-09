<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Models\Tenant\HorseWeightMeasurement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Body-weight log per horse — typically one entry per month.
 * Trend column compares each row to the previous one to surface
 * gains / losses without needing a chart widget.
 */
class WeightMeasurementsRelationManager extends RelationManager
{
    protected static string $relationship = 'weightMeasurements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.horse_weight_measurements');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.horse_weight_measurement');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.horse_weight_measurements');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\DatePicker::make('measured_at')
                        ->label(__('app/horse_weight.form.label.measured_at'))
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('weight_kg')
                        ->label(__('app/horse_weight.form.label.weight_kg'))
                        ->suffix('kg')
                        ->numeric()
                        ->step(0.1)
                        ->minValue(50)
                        ->maxValue(1500)
                        ->required(),
                    Forms\Components\TextInput::make('girth_cm')
                        ->label(__('app/horse_weight.form.label.girth_cm'))
                        ->helperText(__('app/horse_weight.form.helper.girth_cm'))
                        ->suffix('cm')
                        ->numeric()
                        ->step(0.1)
                        ->minValue(50)
                        ->maxValue(300),
                ]),
            Forms\Components\Textarea::make('notes')
                ->label(__('app/horse_weight.form.label.notes'))
                ->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('measured_at')
            ->columns([
                Tables\Columns\TextColumn::make('measured_at')
                    ->label(__('app/horse_weight.table.column.measured_at'))
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_kg')
                    ->label(__('app/horse_weight.table.column.weight_kg'))
                    ->formatStateUsing(fn ($state) => $state.' kg')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('trend')
                    ->label(__('app/horse_weight.table.column.trend'))
                    ->state(function (HorseWeightMeasurement $record): string {
                        $previous = HorseWeightMeasurement::query()
                            ->where('horse_id', $record->horse_id)
                            ->where('measured_at', '<', $record->measured_at)
                            ->orderByDesc('measured_at')
                            ->first();

                        if (! $previous) {
                            return '—';
                        }

                        $diff = (float) $record->weight_kg - (float) $previous->weight_kg;
                        $sign = $diff > 0 ? '+' : '';

                        return $sign.number_format($diff, 1, ',', '').' kg';
                    })
                    ->color(function (HorseWeightMeasurement $record): string {
                        $previous = HorseWeightMeasurement::query()
                            ->where('horse_id', $record->horse_id)
                            ->where('measured_at', '<', $record->measured_at)
                            ->orderByDesc('measured_at')
                            ->first();

                        if (! $previous) {
                            return 'gray';
                        }

                        $diff = (float) $record->weight_kg - (float) $previous->weight_kg;

                        return match (true) {
                            abs($diff) < 5 => 'gray',
                            $diff > 0 => 'success',
                            default => 'warning',
                        };
                    }),
                Tables\Columns\TextColumn::make('girth_cm')
                    ->label(__('app/horse_weight.table.column.girth_cm'))
                    ->formatStateUsing(fn (?string $state) => $state !== null ? $state.' cm' : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('app/horse_weight.table.column.notes'))
                    ->limit(50)
                    ->toggleable(),
            ])
            ->defaultSort('measured_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
