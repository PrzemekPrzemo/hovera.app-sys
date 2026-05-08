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

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.calendar_entries');
    }

    public static function getModelLabel(): string
    {
        return __('models.calendar_entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.calendar_entries');
    }

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/calendar.form.section.time_type'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label(__('app/calendar.form.label.type'))
                        ->options(CalendarEntryType::options())
                        ->default(CalendarEntryType::LessonIndividual->value)
                        ->required()
                        ->reactive(),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label(__('app/calendar.form.label.starts_at'))
                        ->required()
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(__('app/calendar.form.label.ends_at'))
                        ->required()
                        ->seconds(false)
                        ->after('starts_at'),
                ]),

            Forms\Components\Section::make(__('app/calendar.form.section.resources'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('horse_id')
                        ->label(__('app/calendar.form.label.horse'))
                        ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get) => CalendarEntryType::tryFrom((string) $get('type'))?->requiresHorse() ?? false),
                    Forms\Components\Select::make('instructor_id')
                        ->label(__('app/calendar.form.label.instructor'))
                        ->options(fn () => Instructor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get) => CalendarEntryType::tryFrom((string) $get('type'))?->requiresInstructor() ?? false),
                    Forms\Components\Select::make('arena_id')
                        ->label(__('app/calendar.form.label.arena'))
                        ->options(fn () => Arena::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('client_id')
                        ->label(__('app/calendar.form.label.client'))
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ]),

            Forms\Components\Section::make(__('app/calendar.form.section.details'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('app/calendar.form.label.title'))
                        ->maxLength(160),
                    Forms\Components\Select::make('status')
                        ->label(__('app/calendar.form.label.status'))
                        ->options(CalendarEntryStatus::options())
                        ->default(CalendarEntryStatus::Confirmed->value)
                        ->required(),
                    PriceInput::make('price_cents', __('app/calendar.form.label.price')),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/calendar.form.label.notes'))
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
                    ->label(__('app/calendar.table.column.starts_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('app/calendar.table.column.ends_at'))
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/calendar.table.column.type'))
                    ->formatStateUsing(fn (CalendarEntryType $state) => $state->label()),
                Tables\Columns\TextColumn::make('horse.name')
                    ->label(__('app/calendar.table.column.horse'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label(__('app/calendar.table.column.instructor'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('arena.name')
                    ->label(__('app/calendar.table.column.arena'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('app/calendar.table.column.client'))->placeholder('—')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('app/calendar.table.column.status'))
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
                    ->label(__('app/calendar.table.filter.horse'))
                    ->relationship('horse', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('instructor_id')
                    ->label(__('app/calendar.table.filter.instructor'))
                    ->relationship('instructor', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('upcoming')
                    ->label(__('app/calendar.table.filter.upcoming'))
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
