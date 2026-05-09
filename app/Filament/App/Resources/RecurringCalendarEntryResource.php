<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Actions\Calendar\CreateRecurringSeries;
use App\Actions\Calendar\DeleteRecurringSeries;
use App\Enums\CalendarEntryType;
use App\Enums\RecurrencePattern;
use App\Filament\App\Resources\RecurringCalendarEntryResource\Pages;
use App\Filament\Components\PriceInput;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Arena;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Services\Tenancy\TenantRoleGate;
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
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::STABLE_OPS_STAFF;
    }

    protected static ?string $model = RecurringCalendarEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.recurring_entries');
    }

    public static function getModelLabel(): string
    {
        return __('models.recurring_entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.recurring_entries');
    }

    protected static ?int $navigationSort = 35;

    /** @return array<string,string> */
    private static function daysOfWeek(): array
    {
        return [
            '1' => __('app/recurring.days_of_week.1'),
            '2' => __('app/recurring.days_of_week.2'),
            '3' => __('app/recurring.days_of_week.3'),
            '4' => __('app/recurring.days_of_week.4'),
            '5' => __('app/recurring.days_of_week.5'),
            '6' => __('app/recurring.days_of_week.6'),
            '0' => __('app/recurring.days_of_week.0'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/recurring.form.section.basic'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/recurring.form.label.name'))
                        ->required()
                        ->maxLength(160)
                        ->placeholder(__('app/recurring.form.label.name_placeholder')),
                    Forms\Components\Select::make('type')
                        ->label(__('app/recurring.form.label.type'))
                        ->options(collect([
                            CalendarEntryType::LessonIndividual,
                            CalendarEntryType::LessonGroup,
                            CalendarEntryType::Training,
                            CalendarEntryType::Care,
                        ])->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all())
                        ->default(CalendarEntryType::LessonIndividual->value)
                        ->required(),
                    Forms\Components\TimePicker::make('starts_time')
                        ->label(__('app/recurring.form.label.starts_time'))->required()->seconds(false),
                    Forms\Components\TextInput::make('duration_minutes')
                        ->label(__('app/recurring.form.label.duration_minutes'))
                        ->numeric()
                        ->minValue(15)
                        ->default(60)
                        ->required(),
                ]),

            Forms\Components\Section::make(__('app/recurring.form.section.recurrence'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('recurrence_pattern')
                        ->label(__('app/recurring.form.label.pattern'))
                        ->options(RecurrencePattern::options())
                        ->default(RecurrencePattern::Weekly->value)
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('recurrence_interval')
                        ->label(__('app/recurring.form.label.interval'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->helperText(__('app/recurring.form.helper.interval')),
                    Forms\Components\CheckboxList::make('recurrence_days_of_week')
                        ->label(__('app/recurring.form.label.days_of_week'))
                        ->options(self::daysOfWeek())
                        ->columns(4)
                        ->visible(fn (Forms\Get $get) => $get('recurrence_pattern') === RecurrencePattern::Weekly->value)
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('recurrence_starts_on')
                        ->label(__('app/recurring.form.label.recurrence_starts_on'))->required(),
                    Forms\Components\DatePicker::make('recurrence_ends_on')
                        ->label(__('app/recurring.form.label.recurrence_ends_on'))
                        ->after('recurrence_starts_on')
                        ->helperText(__('app/recurring.form.helper.recurrence_ends_on')),
                    Forms\Components\TextInput::make('max_occurrences')
                        ->label(__('app/recurring.form.label.max_occurrences'))
                        ->numeric()
                        ->minValue(1)
                        ->placeholder(__('app/recurring.form.label.max_occurrences_placeholder'))
                        ->helperText(__('app/recurring.form.helper.max_occurrences')),
                ]),

            Forms\Components\Section::make(__('app/recurring.form.section.default_resources'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('horse_id')
                        ->label(__('app/recurring.form.label.horse'))
                        ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('instructor_id')
                        ->label(__('app/recurring.form.label.instructor'))
                        ->options(fn () => Instructor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('arena_id')
                        ->label(__('app/recurring.form.label.arena'))
                        ->options(fn () => Arena::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Select::make('client_id')
                        ->label(__('app/recurring.form.label.client'))
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ]),

            Forms\Components\Section::make(__('app/recurring.form.section.details'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('app/recurring.form.label.title'))->maxLength(160),
                    PriceInput::make('price_cents', __('app/recurring.form.label.price')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/recurring.form.label.is_active'))->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/recurring.form.label.notes'))->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/recurring.table.column.name'))->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/recurring.table.column.type'))
                    ->formatStateUsing(fn (CalendarEntryType $state) => $state->label()),
                Tables\Columns\TextColumn::make('recurrence_pattern')
                    ->label(__('app/recurring.table.column.pattern'))
                    ->formatStateUsing(fn (RecurrencePattern $state, RecurringCalendarEntry $r) => sprintf(
                        '%s × %d',
                        $state->label(),
                        $r->recurrence_interval,
                    )),
                Tables\Columns\TextColumn::make('starts_time')
                    ->label(__('app/recurring.table.column.starts_time'))
                    ->formatStateUsing(fn ($state) => substr((string) $state, 0, 5)),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label(__('app/recurring.table.column.duration_minutes'))->toggleable(),
                Tables\Columns\TextColumn::make('recurrence_starts_on')
                    ->label(__('app/recurring.table.column.recurrence_starts_on'))->date(),
                Tables\Columns\TextColumn::make('recurrence_ends_on')
                    ->label(__('app/recurring.table.column.recurrence_ends_on'))
                    ->date()->placeholder(__('app/recurring.table.column.recurrence_ends_on_empty')),
                Tables\Columns\TextColumn::make('occurrences_count')
                    ->counts('occurrences')
                    ->label(__('app/recurring.table.column.occurrences_count'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('app/recurring.table.column.is_active'))->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('app/recurring.table.filter.status')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('expand')
                    ->label(__('app/recurring.action.expand.label'))
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->action(function (RecurringCalendarEntry $record, CreateRecurringSeries $action) {
                        $result = $action->execute($record);

                        $body = __('app/recurring.action.expand.success_body', ['count' => $result['created']]);
                        if (count($result['skipped_conflicts']) > 0) {
                            $list = implode(', ', array_slice($result['skipped_conflicts'], 0, 5))
                                .(count($result['skipped_conflicts']) > 5 ? '…' : '');
                            $body .= __('app/recurring.action.expand.skipped', ['list' => $list]);
                        }

                        Notification::make()
                            ->success()
                            ->title(__('app/recurring.action.expand.success_title'))
                            ->body($body)
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('cancel_series')
                    ->label(__('app/recurring.action.cancel_series.label'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('app/recurring.action.cancel_series.modal_heading'))
                    ->modalDescription(__('app/recurring.action.cancel_series.modal_description'))
                    ->action(function (RecurringCalendarEntry $record, DeleteRecurringSeries $action) {
                        $result = $action->execute($record);
                        Notification::make()
                            ->success()
                            ->title(__('app/recurring.action.cancel_series.success_title'))
                            ->body(__('app/recurring.action.cancel_series.success_body', ['count' => $result['cancelled']]))
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
