<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Enums\HealthRecordType;
use App\Filament\Components\PriceInput;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class HealthRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'healthRecords';

    protected static ?string $title = 'Opieka i zdrowie';

    protected static ?string $modelLabel = 'wpis';

    protected static ?string $pluralModelLabel = 'Wpisy zdrowotne';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('type')
                    ->label('Typ')
                    ->options(HealthRecordType::options())
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (?string $state, Forms\Get $get, Forms\Set $set) {
                        $months = HealthRecordType::tryFrom((string) $state)?->defaultFollowUpMonths();
                        if ($months !== null && empty($get('next_due_at'))) {
                            $performedAt = $get('performed_at') ? Carbon::parse($get('performed_at')) : now();
                            $set('next_due_at', $performedAt->copy()->addMonths($months)->toDateString());
                        }
                    }),
                Forms\Components\DateTimePicker::make('performed_at')
                    ->label('Data zabiegu')
                    ->seconds(false)
                    ->required()
                    ->default(now()),
            ]),
            Forms\Components\TextInput::make('summary')
                ->label('Krótki opis')
                ->required()
                ->maxLength(255),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('performed_by')->label('Wykonał')->maxLength(255),
                Forms\Components\DatePicker::make('next_due_at')->label('Następny zabieg'),
                PriceInput::make('cost_cents', 'Koszt'),
            ]),
            Forms\Components\Textarea::make('details')->label('Notatki')->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')->label('Data')->date()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (HealthRecordType $state) => $state->label()),
                Tables\Columns\TextColumn::make('summary')->label('Opis')->limit(60),
                Tables\Columns\TextColumn::make('performed_by')->label('Wykonał')->toggleable(),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label('Następny')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (?Carbon $state) => match (true) {
                        $state === null => 'gray',
                        $state->isPast() => 'danger',
                        $state->lte(now()->addDays(7)) => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('performed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(HealthRecordType::options()),
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
