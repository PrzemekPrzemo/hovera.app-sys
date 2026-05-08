<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Enums\HealthRecordType;
use App\Filament\Components\PriceInput;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Specialist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class HealthRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'healthRecords';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('navigation.health_records');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.health_entry');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.health_entries');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('type')
                    ->label(__('app/horse_health.form.label.type'))
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
                    ->label(__('app/horse_health.form.label.performed_at'))
                    ->seconds(false)
                    ->required()
                    ->default(now()),
            ]),
            Forms\Components\TextInput::make('summary')
                ->label(__('app/horse_health.form.label.summary'))
                ->required()
                ->maxLength(255),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('specialist_id')
                    ->label(__('app/horse_health.form.label.specialist'))
                    ->options(fn (Forms\Get $get) => Specialist::query()
                        ->where('is_active', true)
                        ->when(
                            HealthRecordType::tryFrom((string) $get('type'))?->value === 'farrier',
                            fn ($q) => $q->where('type', Specialist::TYPE_FARRIER),
                            fn ($q) => $q->where('type', Specialist::TYPE_VET),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->reactive()
                    ->searchable()
                    ->placeholder(__('app/horse_health.form.label.specialist_placeholder')),
                Forms\Components\TextInput::make('performed_by')
                    ->label(__('app/horse_health.form.label.performed_by'))
                    ->placeholder(__('app/horse_health.form.label.performed_by_placeholder'))
                    ->maxLength(255),
                Forms\Components\DatePicker::make('next_due_at')
                    ->label(__('app/horse_health.form.label.next_due_at')),
                PriceInput::make('cost_cents', __('app/horse_health.form.label.cost')),
            ]),
            Forms\Components\Textarea::make('details')
                ->label(__('app/horse_health.form.label.details'))->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label(__('app/horse_health.table.column.performed_at'))->date()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/horse_health.table.column.type'))
                    ->formatStateUsing(fn (HealthRecordType $state) => $state->label()),
                Tables\Columns\TextColumn::make('summary')
                    ->label(__('app/horse_health.table.column.summary'))->limit(60),
                Tables\Columns\TextColumn::make('performed_by')
                    ->label(__('app/horse_health.table.column.performed_by'))
                    ->getStateUsing(fn (HealthRecord $r) => $r->performedByLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label(__('app/horse_health.table.column.next_due_at'))
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
