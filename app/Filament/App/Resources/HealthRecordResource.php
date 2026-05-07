<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\HealthRecordType;
use App\Filament\App\Resources\HealthRecordResource\Pages;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

class HealthRecordResource extends Resource
{
    protected static ?string $model = HealthRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?string $navigationLabel = 'Opieka i zdrowie';

    protected static ?string $modelLabel = 'wpis zdrowotny';

    protected static ?string $pluralModelLabel = 'Opieka i zdrowie';

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Wpis')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('horse_id')
                        ->label('Koń')
                        ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('type')
                        ->label('Typ')
                        ->options(HealthRecordType::options())
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(self::suggestNextDue()),
                    Forms\Components\DateTimePicker::make('performed_at')
                        ->label('Data zabiegu')
                        ->seconds(false)
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('performed_by')
                        ->label('Wykonał (lekarz / kowal / firma)')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('summary')
                        ->label('Krótki opis')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Szczepienie tężec + grypa'),
                    Forms\Components\DatePicker::make('next_due_at')
                        ->label('Następny zabieg')
                        ->helperText('Dzięki temu pojawi się alert na dashboardzie.'),
                    Forms\Components\TextInput::make('cost_cents')
                        ->label('Koszt (gr)')
                        ->numeric()
                        ->minValue(0),
                ]),
            Forms\Components\Section::make('Szczegóły')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('details')
                        ->label('Notatki / leki / zalecenia')
                        ->rows(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')->label('Data')->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('horse.name')->label('Koń')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (HealthRecordType $state) => $state->label()),
                Tables\Columns\TextColumn::make('summary')->label('Opis')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('performed_by')->label('Wykonał')->toggleable(),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label('Następny')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (?Carbon $state) => match (true) {
                        $state === null => 'gray',
                        $state->isPast() => 'danger',
                        $state->lte(now()->addDays(7)) => 'warning',
                        $state->lte(now()->addDays(30)) => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('cost_cents')
                    ->label('Koszt')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('performed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(HealthRecordType::options()),
                Tables\Filters\SelectFilter::make('horse_id')
                    ->label('Koń')
                    ->relationship('horse', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('overdue')
                    ->label('Przeterminowane (next due in past)')
                    ->query(fn ($query) => $query->overdue()),
                Tables\Filters\Filter::make('due_30')
                    ->label('Następny w 30 dni')
                    ->query(fn ($query) => $query->dueWithin(30)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('health.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('health.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('health.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHealthRecords::route('/'),
            'create' => Pages\CreateHealthRecord::route('/create'),
            'edit' => Pages\EditHealthRecord::route('/{record}/edit'),
        ];
    }

    /**
     * When the user picks a type, pre-fill `next_due_at` with a sensible
     * default so they don't have to compute "yearly from today" manually.
     * Only fills if the field is currently empty — won't clobber user input.
     */
    private static function suggestNextDue(): callable
    {
        return function (?string $state, Forms\Get $get, Forms\Set $set) {
            if (! $state) {
                return;
            }

            $type = HealthRecordType::tryFrom($state);
            $months = $type?->defaultFollowUpMonths();
            $current = $get('next_due_at');

            if ($months !== null && empty($current)) {
                $performedAt = $get('performed_at') ? Carbon::parse($get('performed_at')) : now();
                $set('next_due_at', $performedAt->copy()->addMonths($months)->toDateString());
            }
        };
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'HealthRecord', (string) $record->getKey(), [
                'horse_id' => $record->horse_id,
                'type' => $record->type instanceof HealthRecordType ? $record->type->value : (string) $record->type,
            ]);
        };
    }
}
