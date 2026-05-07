<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Filament\App\Resources\CalendarEntryResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Tenant\Arena;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CalendarEntryResource extends Resource
{
    protected static ?string $model = CalendarEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Kalendarz';

    protected static ?string $navigationLabel = 'Rezerwacje';

    protected static ?string $modelLabel = 'rezerwacja';

    protected static ?string $pluralModelLabel = 'Rezerwacje';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Czas i typ')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Typ')
                        ->options(CalendarEntryType::options())
                        ->default(CalendarEntryType::LessonIndividual->value)
                        ->required()
                        ->reactive(),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Początek')
                        ->required()
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Koniec')
                        ->required()
                        ->seconds(false)
                        ->after('starts_at'),
                ]),

            Forms\Components\Section::make('Zasoby')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('horse_id')
                        ->label('Koń')
                        ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get) => CalendarEntryType::tryFrom((string) $get('type'))?->requiresHorse() ?? false),
                    Forms\Components\Select::make('instructor_id')
                        ->label('Instruktor')
                        ->options(fn () => Instructor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get) => CalendarEntryType::tryFrom((string) $get('type'))?->requiresInstructor() ?? false),
                    Forms\Components\Select::make('arena_id')
                        ->label('Ujeżdżalnia')
                        ->options(fn () => Arena::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('client_id')
                        ->label('Klient')
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ]),

            Forms\Components\Section::make('Szczegóły')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Tytuł (dla wydarzeń / blokad)')
                        ->maxLength(160),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(CalendarEntryStatus::options())
                        ->default(CalendarEntryStatus::Confirmed->value)
                        ->required(),
                    PriceInput::make('price_cents', 'Cena'),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notatki')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Początek')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Koniec')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (CalendarEntryType $state) => $state->label()),
                Tables\Columns\TextColumn::make('horse.name')->label('Koń')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('instructor.name')->label('Instruktor')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('arena.name')->label('Ujeżdżalnia')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('client.name')->label('Klient')->placeholder('—')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (CalendarEntryStatus $state) => $state->label())
                    ->colors([
                        'warning' => fn ($state) => $state === CalendarEntryStatus::Requested->value,
                        'success' => fn ($state) => $state === CalendarEntryStatus::Confirmed->value
                            || $state === CalendarEntryStatus::Completed->value,
                        'danger' => fn ($state) => $state === CalendarEntryStatus::Cancelled->value
                            || $state === CalendarEntryStatus::NoShow->value,
                    ]),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(CalendarEntryType::options()),
                Tables\Filters\SelectFilter::make('status')->options(CalendarEntryStatus::options()),
                Tables\Filters\SelectFilter::make('horse_id')
                    ->label('Koń')
                    ->relationship('horse', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('instructor_id')
                    ->label('Instruktor')
                    ->relationship('instructor', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Tylko nadchodzące')
                    ->query(fn ($query) => $query->where('starts_at', '>=', now())),
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
            'index' => Pages\ListCalendarEntries::route('/'),
            'create' => Pages\CreateCalendarEntry::route('/create'),
            'edit' => Pages\EditCalendarEntry::route('/{record}/edit'),
        ];
    }
}
