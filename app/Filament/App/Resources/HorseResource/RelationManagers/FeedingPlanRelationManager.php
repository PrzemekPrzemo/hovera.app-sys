<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Enums\FeedingMeal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-horse feeding schedule. Stable staff sees this on the horse profile;
 * the owner reads it via the client portal (read-only) so they know
 * exactly what their boarder eats day to day.
 */
class FeedingPlanRelationManager extends RelationManager
{
    protected static string $relationship = 'feedingPlanItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.feeding_plan');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.feeding_plan_item');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.feeding_plan');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('meal')
                        ->label(__('app/feeding_plan.form.label.meal'))
                        ->options(FeedingMeal::options())
                        ->required()
                        ->default(FeedingMeal::Breakfast->value),
                    Forms\Components\TextInput::make('feed_type')
                        ->label(__('app/feeding_plan.form.label.feed_type'))
                        ->placeholder(__('app/feeding_plan.form.label.feed_type_placeholder'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('amount_kg')
                        ->label(__('app/feeding_plan.form.label.amount'))
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->maxValue(50)
                        ->required(),
                    Forms\Components\TextInput::make('unit')
                        ->label(__('app/feeding_plan.form.label.unit'))
                        ->default('kg')
                        ->maxLength(20)
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/feeding_plan.form.label.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/feeding_plan.form.label.is_active'))
                        ->default(true),
                ]),
            Forms\Components\Textarea::make('notes')
                ->label(__('app/feeding_plan.form.label.notes'))
                ->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('feed_type')
            ->columns([
                Tables\Columns\TextColumn::make('meal')
                    ->label(__('app/feeding_plan.table.column.meal'))
                    ->badge()
                    ->formatStateUsing(fn (FeedingMeal $state) => $state->emoji().' '.$state->label())
                    ->sortable(query: fn ($query, $direction) => $query
                        ->orderByRaw("FIELD(meal, 'breakfast','midday','evening','night') {$direction}")),
                Tables\Columns\TextColumn::make('feed_type')
                    ->label(__('app/feeding_plan.table.column.feed_type'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('amount_kg')
                    ->label(__('app/feeding_plan.table.column.amount'))
                    ->getStateUsing(fn ($record) => $record->amountFormatted()),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('app/feeding_plan.table.column.is_active')),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('app/feeding_plan.table.column.notes'))
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('meal')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
