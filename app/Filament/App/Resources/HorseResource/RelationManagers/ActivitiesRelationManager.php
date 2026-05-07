<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Enums\StableActivityType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Activity log per koń: karmienia, sprzątania boksu, padoki, transport.
 * Widoczne na karcie konia (`/app/horses/{id}/edit`) — i replikowane do
 * portalu klienta sekcji "Co robimy z Twoim koniem".
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Aktywności';

    protected static ?string $modelLabel = 'aktywność';

    protected static ?string $pluralModelLabel = 'Aktywności';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Typ')
                        ->options(StableActivityType::options())
                        ->required()
                        ->default(StableActivityType::Feeding->value),
                    Forms\Components\DateTimePicker::make('performed_at')
                        ->label('Kiedy')
                        ->seconds(false)
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('performed_by')
                        ->label('Wykonał (imię stajennego)')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('cost_cents')
                        ->label('Dodatkowy koszt (gr, opcjonalnie)')
                        ->numeric()->minValue(0)
                        ->helperText('Wpisz tylko gdy aktywność naliczyła koszt poza ryczałtem (np. dodatkowe siano, transport).'),
                ]),
            Forms\Components\TextInput::make('summary')
                ->label('Krótki opis')
                ->maxLength(200)
                ->placeholder('np. "Wypuszczenie 9:00-12:00, padok wschodni"'),
            Forms\Components\Textarea::make('details')->label('Notatki')->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')->label('Data')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (StableActivityType $state) => $state->label())
                    ->colors([
                        'success' => StableActivityType::Feeding->value,
                        'primary' => StableActivityType::Grooming->value,
                        'warning' => StableActivityType::Turnout->value,
                        'danger' => StableActivityType::TransportEvent->value,
                    ]),
                Tables\Columns\TextColumn::make('summary')->label('Opis')->limit(60),
                Tables\Columns\TextColumn::make('performed_by')->label('Wykonał')->toggleable(),
                Tables\Columns\TextColumn::make('cost_cents')
                    ->label('Koszt')
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
