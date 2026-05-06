<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Actions\Calendar\CreateRecurringSeries;
use App\Actions\Calendar\DeleteRecurringSeries;
use App\Enums\CalendarEntryType;
use App\Enums\RecurrencePattern;
use App\Filament\App\Resources\RecurringCalendarEntryResource\Pages;
use App\Models\Tenant\Arena;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use App\Models\Tenant\RecurringCalendarEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecurringCalendarEntryResource extends Resource
{
    protected static ?string $model = RecurringCalendarEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Kalendarz';

    protected static ?string $navigationLabel = 'Cykliczne zajęcia';

    protected static ?string $modelLabel = 'cykliczne zajęcie';

    protected static ?string $pluralModelLabel = 'Cykliczne zajęcia';

    protected static ?int $navigationSort = 35;

    /** @var array<int,string> */
    private const DAYS_OF_WEEK = [
        '1' => 'Poniedziałek',
        '2' => 'Wtorek',
        '3' => 'Środa',
        '4' => 'Czwartek',
        '5' => 'Piątek',
        '6' => 'Sobota',
        '0' => 'Niedziela',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa serii')
                        ->required()
                        ->maxLength(160)
                        ->placeholder('Szkółka pon. 17:00'),
                    Forms\Components\Select::make('type')
                        ->label('Typ')
                        ->options(collect([
                            CalendarEntryType::LessonIndividual,
                            CalendarEntryType::LessonGroup,
                            CalendarEntryType::Training,
                            CalendarEntryType::Care,
                        ])->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all())
                        ->default(CalendarEntryType::LessonIndividual->value)
                        ->required(),
                    Forms\Components\TimePicker::make('starts_time')->label('Godzina rozpoczęcia')->required()->seconds(false),
                    Forms\Components\TextInput::make('duration_minutes')
                        ->label('Czas trwania (min)')
                        ->numeric()
                        ->minValue(15)
                        ->default(60)
                        ->required(),
                ]),

            Forms\Components\Section::make('Powtarzalność')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('recurrence_pattern')
                        ->label('Wzorzec')
                        ->options(RecurrencePattern::options())
                        ->default(RecurrencePattern::Weekly->value)
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('recurrence_interval')
                        ->label('Co ile')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->helperText('1 = każdy, 2 = co drugi…'),
                    Forms\Components\CheckboxList::make('recurrence_days_of_week')
                        ->label('Dni tygodnia')
                        ->options(self::DAYS_OF_WEEK)
                        ->columns(4)
                        ->visible(fn (Forms\Get $get) => $get('recurrence_pattern') === RecurrencePattern::Weekly->value)
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('recurrence_starts_on')->label('Od')->required(),
                    Forms\Components\DatePicker::make('recurrence_ends_on')
                        ->label('Do (opcjonalne)')
                        ->after('recurrence_starts_on')
                        ->helperText('Puste = bez końca; expander generuje max 365 wystąpień jednorazowo.'),
                    Forms\Components\TextInput::make('max_occurrences')
                        ->label('Limit wystąpień')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('np. 26')
                        ->helperText('Alternatywa do daty końcowej.'),
                ]),

            Forms\Components\Section::make('Domyślne zasoby')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('horse_id')
                        ->label('Koń')
                        ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('instructor_id')
                        ->label('Instruktor')
                        ->options(fn () => Instructor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
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
                    Forms\Components\TextInput::make('title')->label('Tytuł zajęć')->maxLength(160),
                    Forms\Components\TextInput::make('price_cents')->label('Cena (gr)')->numeric()->minValue(0),
                    Forms\Components\Toggle::make('is_active')->label('Aktywna seria')->default(true),
                    Forms\Components\Textarea::make('notes')->label('Notatki')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (CalendarEntryType $state) => $state->label()),
                Tables\Columns\TextColumn::make('recurrence_pattern')
                    ->label('Wzorzec')
                    ->formatStateUsing(fn (RecurrencePattern $state, RecurringCalendarEntry $r) => sprintf(
                        '%s × %d',
                        $state->label(),
                        $r->recurrence_interval,
                    )),
                Tables\Columns\TextColumn::make('starts_time')->label('Godz.')->formatStateUsing(fn ($state) => substr((string) $state, 0, 5)),
                Tables\Columns\TextColumn::make('duration_minutes')->label('Min')->toggleable(),
                Tables\Columns\TextColumn::make('recurrence_starts_on')->label('Od')->date(),
                Tables\Columns\TextColumn::make('recurrence_ends_on')->label('Do')->date()->placeholder('— bez końca —'),
                Tables\Columns\TextColumn::make('occurrences_count')
                    ->counts('occurrences')
                    ->label('Wystąpień')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywna')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('expand')
                    ->label('Wygeneruj wystąpienia')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->action(function (RecurringCalendarEntry $record, CreateRecurringSeries $action) {
                        $result = $action->execute($record);

                        $body = "Utworzono {$result['created']} wystąpień.";
                        if (count($result['skipped_conflicts']) > 0) {
                            $body .= ' Pominięto z powodu konfliktu: '
                                .implode(', ', array_slice($result['skipped_conflicts'], 0, 5))
                                .(count($result['skipped_conflicts']) > 5 ? '…' : '').'.';
                        }

                        Notification::make()
                            ->success()
                            ->title('Seria rozwinięta')
                            ->body($body)
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('cancel_series')
                    ->label('Anuluj serię')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anuluj całą serię')
                    ->modalDescription('Wystąpienia w przeszłości zostaną zachowane, przyszłe odwołane.')
                    ->action(function (RecurringCalendarEntry $record, DeleteRecurringSeries $action) {
                        $result = $action->execute($record);
                        Notification::make()
                            ->success()
                            ->title('Seria anulowana')
                            ->body("Anulowano {$result['cancelled']} przyszłych wystąpień.")
                            ->send();
                    }),
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
            'index' => Pages\ListRecurringCalendarEntries::route('/'),
            'create' => Pages\CreateRecurringCalendarEntry::route('/create'),
            'edit' => Pages\EditRecurringCalendarEntry::route('/{record}/edit'),
        ];
    }
}
