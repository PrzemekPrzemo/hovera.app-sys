<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\HealthRecordType;
use App\Filament\App\Resources\TreatmentTemplateResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\TreatmentTemplate;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TreatmentTemplateResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::SPECIALIST_STAFF;
    }

    protected static ?string $model = TreatmentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';

    protected static ?int $navigationSort = 38;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.treatment_templates');
    }

    public static function getModelLabel(): string
    {
        return __('models.treatment_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.treatment_templates');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/treatment_template.form.section.template'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/treatment_template.form.label.name'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Select::make('type')
                        ->label(__('app/treatment_template.form.label.type'))
                        ->options(HealthRecordType::options())
                        ->required(),
                    Forms\Components\TextInput::make('interval_days')
                        ->label(__('app/treatment_template.form.label.interval_days'))
                        ->helperText(__('app/treatment_template.form.helper.interval_days'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/treatment_template.form.label.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('default_summary')
                        ->label(__('app/treatment_template.form.label.default_summary'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('default_notes')
                        ->label(__('app/treatment_template.form.label.default_notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/treatment_template.form.label.is_active'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/treatment_template.table.column.name'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/treatment_template.table.column.type'))
                    ->formatStateUsing(fn (HealthRecordType $state) => $state->label()),
                Tables\Columns\TextColumn::make('interval_days')
                    ->label(__('app/treatment_template.table.column.interval'))
                    ->formatStateUsing(fn (?int $state) => $state ? $state.' '.__('app/treatment_template.table.days') : '—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('app/treatment_template.table.column.is_active')),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(HealthRecordType::options()),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreatmentTemplates::route('/'),
            'create' => Pages\CreateTreatmentTemplate::route('/create'),
            'edit' => Pages\EditTreatmentTemplate::route('/{record}/edit'),
        ];
    }
}
