<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\HealthRecordType;
use App\Filament\App\Resources\HealthRecordResource\Pages;
use App\Filament\Components\PriceInput;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Specialist;
use App\Models\Tenant\TreatmentTemplate;
use App\Services\Tenancy\TenantRoleGate;
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
use Illuminate\Support\HtmlString;

class HealthRecordResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::HORSE_AND_CARE_STAFF;
    }

    protected static ?string $model = HealthRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.health_records');
    }

    public static function getModelLabel(): string
    {
        return __('models.health_record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.health_records');
    }

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/health.form.section.entry'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('horse_id')
                        ->label(__('app/health.form.label.horse'))
                        ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->reactive()
                        ->searchable(),
                    Forms\Components\Placeholder::make('horse_identification')
                        ->label(__('app/health.form.label.horse_identification'))
                        ->content(self::renderHorseIdentification())
                        ->visible(fn (Forms\Get $get) => $get('horse_id') !== null),
                    Forms\Components\Select::make('treatment_template_id')
                        ->label(__('app/health.form.label.template'))
                        ->helperText(__('app/health.form.helper.template'))
                        ->options(fn () => TreatmentTemplate::query()
                            ->active()
                            ->orderBy('sort_order')
                            ->pluck('name', 'id'))
                        ->dehydrated(false)
                        ->placeholder(__('app/health.form.label.template_placeholder'))
                        ->reactive()
                        ->afterStateUpdated(self::applyTemplate())
                        ->columnSpanFull(),
                    Forms\Components\Select::make('type')
                        ->label(__('app/health.form.label.type'))
                        ->options(HealthRecordType::options())
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(self::suggestNextDue()),
                    Forms\Components\DateTimePicker::make('performed_at')
                        ->label(__('app/health.form.label.performed_at'))
                        ->seconds(false)
                        ->required()
                        ->default(now()),
                    Forms\Components\Select::make('specialist_id')
                        ->label(__('app/health.form.label.specialist'))
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
                        ->placeholder(__('app/health.form.label.specialist_placeholder'))
                        ->helperText(__('app/health.form.helper.specialist')),
                    Forms\Components\TextInput::make('performed_by')
                        ->label(__('app/health.form.label.performed_by'))
                        ->placeholder(__('app/health.form.label.performed_by_placeholder'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('summary')
                        ->label(__('app/health.form.label.summary'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder(__('app/health.form.label.summary_placeholder')),
                    Forms\Components\DatePicker::make('next_due_at')
                        ->label(__('app/health.form.label.next_due_at'))
                        ->helperText(__('app/health.form.helper.next_due_at')),
                    PriceInput::make('cost_cents', __('app/health.form.label.cost')),
                ]),
            Forms\Components\Section::make(__('app/health.form.section.details'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('details')
                        ->label(__('app/health.form.label.details'))
                        ->rows(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label(__('app/health.table.column.performed_at'))->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('horse.name')
                    ->label(__('app/health.table.column.horse'))->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/health.table.column.type'))
                    ->formatStateUsing(fn (HealthRecordType $state) => $state->label()),
                Tables\Columns\TextColumn::make('summary')
                    ->label(__('app/health.table.column.summary'))->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('performed_by')
                    ->label(__('app/health.table.column.performed_by'))
                    ->getStateUsing(fn (HealthRecord $r) => $r->performedByLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label(__('app/health.table.column.next_due_at'))
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
                    ->label(__('app/health.table.column.cost'))
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('performed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(HealthRecordType::options()),
                Tables\Filters\SelectFilter::make('horse_id')
                    ->label(__('app/health.table.filter.horse'))
                    ->relationship('horse', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('overdue')
                    ->label(__('app/health.table.filter.overdue'))
                    ->query(fn ($query) => $query->overdue()),
                Tables\Filters\Filter::make('due_30')
                    ->label(__('app/health.table.filter.due_30'))
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

    /**
     * Write gate dla klinicznych wpisów (G4 z audytu ról). Tylko vet,
     * admin, manager mogą tworzyć/edytować/kasować HealthRecord — to
     * dane medyczne i ich integralność wymaga medycznej autoryzacji.
     *
     * Read access (`allowedRoles() = HORSE_AND_CARE_STAFF`) pozostaje
     * dla całej care staffy — instruktor musi widzieć historię zdrowotną
     * konia przed lekcją (czy nie był ostatnio chory).
     *
     * Master admin override przez Filament canCreate default → poprzez
     * isAnyOf w gate. Dodajemy explicit master-admin branch dla parity
     * z `RestrictedByTenantRole::canAccess()`.
     */
    public static function canCreate(): bool
    {
        return self::canWriteClinical();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canWriteClinical();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canWriteClinical();
    }

    /** Public dla testów — single source of truth dla 3 powyższych. */
    public static function canWriteClinical(): bool
    {
        return app(TenantRoleGate::class)->allows(TenantRoleGate::CLINICAL_WRITE_STAFF);
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
     * When the user picks a TreatmentTemplate, fill type / summary /
     * details / next_due_at from the template. Always overwrites — the
     * user explicitly chose a preset, so they want its values.
     */
    /**
     * Inline horse identification (microchip / passport / UELN) widoczne
     * pod selectem konia w formularzu. Wet musi zweryfikować tożsamość
     * konia przy zabiegu — chcemy żeby dane były na ekranie zaraz po
     * wyborze, bez przeklikania do HorseResource.
     *
     * Zwracamy `callable(Get $get): HtmlString` — Placeholder Filament'a
     * akceptuje closure z reactive context'em.
     */
    public static function renderHorseIdentification(): callable
    {
        return fn (Forms\Get $get): HtmlString => self::renderHorseIdentificationFor($get('horse_id'));
    }

    /**
     * Czysta implementacja (testowalna bez Forms\Get) — przyjmuje horse_id,
     * zwraca HTML do osadzenia w Placeholder content.
     */
    public static function renderHorseIdentificationFor(?string $horseId): HtmlString
    {
        if (! $horseId) {
            return new HtmlString('');
        }

        $horse = Horse::query()->find($horseId);
        if ($horse === null) {
            return new HtmlString(
                '<span class="text-sm text-gray-500">'.e(__('app/health.form.horse_identification.missing')).'</span>'
            );
        }

        $rows = [
            __('app/health.form.horse_identification.microchip') => $horse->microchip,
            __('app/health.form.horse_identification.passport') => $horse->passport_number,
            __('app/health.form.horse_identification.ueln') => $horse->ueln,
        ];

        $html = '<dl class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">';
        $shown = 0;
        foreach ($rows as $label => $value) {
            $html .= '<div>';
            $html .= '<dt class="text-gray-500 uppercase tracking-wide">'.e($label).'</dt>';
            if ($value) {
                $html .= '<dd class="font-mono text-sm text-gray-900 dark:text-gray-100">'.e((string) $value).'</dd>';
                $shown++;
            } else {
                $html .= '<dd class="text-gray-400">—</dd>';
            }
            $html .= '</div>';
        }
        $html .= '</dl>';

        if ($shown === 0) {
            $html .= '<div class="mt-2 text-xs text-amber-700 dark:text-amber-400">'
                .e(__('app/health.form.horse_identification.empty_warning')).'</div>';
        }

        return new HtmlString($html);
    }

    private static function applyTemplate(): callable
    {
        return function (?string $state, Forms\Get $get, Forms\Set $set) {
            if (! $state) {
                return;
            }

            $template = TreatmentTemplate::query()->find($state);
            if (! $template) {
                return;
            }

            $set('type', $template->type->value);

            if ($template->default_summary) {
                $set('summary', $template->default_summary);
            }
            if ($template->default_notes) {
                $set('details', $template->default_notes);
            }

            if ($template->interval_days !== null) {
                $performedAt = $get('performed_at') ? Carbon::parse($get('performed_at')) : now();
                $set('next_due_at', $performedAt->copy()->addDays($template->interval_days)->toDateString());
            }
        };
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
