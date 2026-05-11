<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Central\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('navigation.plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public static function getModelLabel(): string
    {
        return __('models.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.plans');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/plan.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('admin/plan.form.helper.code')),
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\Select::make('currency')
                        ->options(['PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD'])
                        ->default('PLN')
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText(__('admin/plan.form.helper.sort_order')),
                ]),
            Forms\Components\Section::make(__('admin/plan.form.section.pricing'))
                ->columns(2)
                ->schema([
                    PriceInput::make('price_monthly_cents', __('admin/plan.form.label.price_monthly')),
                    PriceInput::make('price_yearly_cents', __('admin/plan.form.label.price_yearly'))
                        ->helperText(__('admin/plan.form.helper.price_yearly')),
                    PriceInput::make('onboarding_fee_cents', __('admin/plan.form.label.onboarding_fee'))
                        ->helperText(__('admin/plan.form.helper.onboarding_fee'))
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make(__('admin/plan.form.section.stripe'))
                ->description(__('admin/plan.form.section.stripe_description'))
                ->columns(2)
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('stripe_price_monthly_id')
                        ->label(__('admin/plan.form.label.stripe_price_monthly_id'))
                        ->maxLength(120)
                        ->placeholder('price_1Abc...')
                        ->helperText(__('admin/plan.form.helper.stripe_price_monthly_id')),
                    Forms\Components\TextInput::make('stripe_price_yearly_id')
                        ->label(__('admin/plan.form.label.stripe_price_yearly_id'))
                        ->maxLength(120)
                        ->placeholder('price_1Xyz...')
                        ->helperText(__('admin/plan.form.helper.stripe_price_yearly_id')),
                ]),
            Forms\Components\Section::make(__('admin/plan.form.section.limits'))
                ->description(__('admin/plan.form.section.limits_description'))
                ->schema([
                    Forms\Components\KeyValue::make('limits')
                        ->keyLabel(__('admin/plan.form.label.kv_key'))
                        ->valueLabel(__('admin/plan.form.label.kv_value'))
                        ->reorderable(false)
                        ->helperText(__('admin/plan.form.helper.limits')),
                ]),
            Forms\Components\Section::make(__('admin/plan.form.section.features'))
                ->description(__('admin/plan.form.section.features_description'))
                ->schema([
                    Forms\Components\KeyValue::make('features')
                        ->keyLabel(__('admin/plan.form.label.kv_key'))
                        ->valueLabel(__('admin/plan.form.label.kv_value'))
                        ->reorderable(false)
                        ->helperText(__('admin/plan.form.helper.features')),
                ]),
            Forms\Components\Section::make(__('admin/plan.form.section.visibility'))
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('admin/plan.form.label.is_active'))
                        ->default(true)
                        ->helperText(__('admin/plan.form.helper.is_active')),
                    Forms\Components\Toggle::make('is_public')
                        ->label(__('admin/plan.form.label.is_public'))
                        ->default(true)
                        ->helperText(__('admin/plan.form.helper.is_public')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price_monthly_cents')
                    ->label(__('admin/plan.table.column.price_monthly'))
                    ->formatStateUsing(fn (?int $state, Plan $r): string => $state === null ? '—' : number_format($state / 100, 0, ',', ' ').' '.$r->currency),
                Tables\Columns\TextColumn::make('price_yearly_cents')
                    ->label(__('admin/plan.table.column.price_yearly'))
                    ->formatStateUsing(fn (?int $state, Plan $r): string => $state === null ? '—' : number_format($state / 100, 0, ',', ' ').' '.$r->currency),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label(__('admin/plan.table.column.tenants_count'))
                    ->counts('tenants'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('admin/plan.table.column.is_active_short')),
                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label(__('admin/plan.table.column.is_public_short')),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->before(function (Plan $record) {
                    if ($record->tenants()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title(__('admin/plan.action.delete_blocked_title'))
                            ->body(__('admin/plan.action.delete_blocked_body', ['count' => $record->tenants()->count()]))
                            ->send();
                        $this->halt();
                    }
                }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PlanResource\RelationManagers\AddonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
