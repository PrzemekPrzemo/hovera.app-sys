<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Enums\StableActivityType;
use App\Filament\Components\PriceInput;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Activity log per koń: karmienia, sprzątania boksu, padoki, transport.
 * Widoczne na karcie konia (`/app/horses/{id}/edit`) — i replikowane do
 * portalu klienta sekcji "Co robimy z Twoim koniem".
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.activities');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.activity');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.activities');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label(__('app/horse_activity.form.label.type'))
                        ->options(StableActivityType::options())
                        ->required()
                        ->default(StableActivityType::Feeding->value),
                    Forms\Components\DateTimePicker::make('performed_at')
                        ->label(__('app/horse_activity.form.label.performed_at'))
                        ->seconds(false)
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('performed_by')
                        ->label(__('app/horse_activity.form.label.performed_by'))
                        ->maxLength(120),
                    PriceInput::make('cost_cents', __('app/horse_activity.form.label.cost'))
                        ->helperText(__('app/horse_activity.form.helper.cost')),
                ]),
            Forms\Components\TextInput::make('summary')
                ->label(__('app/horse_activity.form.label.summary'))
                ->maxLength(200)
                ->placeholder(__('app/horse_activity.form.label.summary_placeholder')),
            Forms\Components\Textarea::make('details')
                ->label(__('app/horse_activity.form.label.details'))->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label(__('app/horse_activity.table.column.performed_at'))
                    ->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/horse_activity.table.column.type'))
                    ->formatStateUsing(fn (StableActivityType $state) => $state->label())
                    ->colors([
                        'success' => StableActivityType::Feeding->value,
                        'primary' => StableActivityType::Grooming->value,
                        'warning' => StableActivityType::Turnout->value,
                        'danger' => StableActivityType::TransportEvent->value,
                    ]),
                Tables\Columns\TextColumn::make('summary')
                    ->label(__('app/horse_activity.table.column.summary'))->limit(60),
                Tables\Columns\TextColumn::make('performed_by')
                    ->label(__('app/horse_activity.table.column.performed_by'))->toggleable(),
                Tables\Columns\TextColumn::make('cost_cents')
                    ->label(__('app/horse_activity.table.column.cost'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->toggleable(),
            ])
            ->defaultSort('performed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(StableActivityType::options()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
