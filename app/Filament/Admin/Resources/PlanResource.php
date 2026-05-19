<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\TenantType;
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
                    Forms\Components\Select::make('audience')
                        ->label(__('admin/plan.form.label.audience'))
                        ->options(TenantType::options())
                        ->default(TenantType::Stable->value)
                        ->required()
                        ->helperText(__('admin/plan.form.helper.audience')),
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
            Forms\Components\Section::make(__('admin/plan.form.section.multi_currency'))
                ->description(__('admin/plan.form.section.multi_currency_description'))
                ->collapsible()
                // Enterprise (custom contract) — bez fixed pricing,
                // multi-currency overlay nie ma sensu.
                ->hidden(fn (Forms\Get $get) => self::isEnterprisePlan($get))
                ->schema([
                    Forms\Components\Repeater::make('prices_per_currency')
                        ->label('')
                        ->addActionLabel(__('admin/plan.form.label.multi_currency_add'))
                        ->columns(2)
                        ->reorderable(false)
                        ->defaultItems(0)
                        // Klucz repeatera == currency code; serializujemy
                        // do {EUR: {monthly_cents, yearly_cents, stripe_*}, ...}.
                        ->afterStateHydrated(function (Forms\Components\Repeater $component, $state) {
                            if (! is_array($state) || $state === []) {
                                $component->state([]);

                                return;
                            }
                            $items = [];
                            foreach ($state as $currency => $row) {
                                if (! is_array($row)) {
                                    continue;
                                }
                                $items[] = [
                                    'currency' => is_string($currency) ? strtoupper($currency) : '',
                                    'monthly_cents' => $row['monthly_cents'] ?? null,
                                    'yearly_cents' => $row['yearly_cents'] ?? null,
                                    'stripe_price_monthly_id' => $row['stripe_price_monthly_id'] ?? null,
                                    'stripe_price_yearly_id' => $row['stripe_price_yearly_id'] ?? null,
                                ];
                            }
                            $component->state($items);
                        })
                        // Repeater::setUp ustawia `mutateDehydratedStateUsing`
                        // który ZAWSZE array_values() po naszym
                        // `dehydrateStateUsing`. Żeby zachować klucze walut,
                        // używamy `mutateDehydratedStateUsing` (nadpisuje
                        // domyślny).
                        ->mutateDehydratedStateUsing(function ($state) {
                            if (! is_array($state)) {
                                return null;
                            }
                            $out = [];
                            foreach ($state as $row) {
                                if (! is_array($row)) {
                                    continue;
                                }
                                $currency = strtoupper((string) ($row['currency'] ?? ''));
                                if ($currency === '') {
                                    continue;
                                }
                                $out[$currency] = array_filter([
                                    'monthly_cents' => isset($row['monthly_cents']) ? (int) $row['monthly_cents'] : null,
                                    'yearly_cents' => isset($row['yearly_cents']) ? (int) $row['yearly_cents'] : null,
                                    'stripe_price_monthly_id' => $row['stripe_price_monthly_id'] ?? null,
                                    'stripe_price_yearly_id' => $row['stripe_price_yearly_id'] ?? null,
                                ], static fn ($v) => $v !== null && $v !== '');
                            }

                            return $out !== [] ? $out : null;
                        })
                        ->schema([
                            Forms\Components\Select::make('currency')
                                ->label(__('admin/plan.form.label.multi_currency_currency'))
                                ->options(self::currencyOptionsForOverlay())
                                ->required()
                                ->helperText(__('admin/plan.form.helper.multi_currency_currency')),
                            Forms\Components\Group::make()->schema([]), // spacer
                            Forms\Components\TextInput::make('monthly_cents')
                                ->label(__('admin/plan.form.label.multi_currency_monthly'))
                                ->numeric()
                                ->minValue(0)
                                ->helperText(__('admin/plan.form.helper.multi_currency_monthly')),
                            Forms\Components\TextInput::make('yearly_cents')
                                ->label(__('admin/plan.form.label.multi_currency_yearly'))
                                ->numeric()
                                ->minValue(0)
                                ->helperText(__('admin/plan.form.helper.multi_currency_yearly')),
                            Forms\Components\TextInput::make('stripe_price_monthly_id')
                                ->label(__('admin/plan.form.label.multi_currency_stripe_monthly'))
                                ->disabled()
                                ->dehydrated()
                                ->helperText(__('admin/plan.form.helper.multi_currency_stripe_monthly')),
                            Forms\Components\TextInput::make('stripe_price_yearly_id')
                                ->label(__('admin/plan.form.label.multi_currency_stripe_yearly'))
                                ->disabled()
                                ->dehydrated()
                                ->helperText(__('admin/plan.form.helper.multi_currency_stripe_yearly')),
                        ]),
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
                Tables\Columns\TextColumn::make('audience')
                    ->label(__('admin/plan.table.column.audience'))
                    ->badge()
                    ->sortable()
                    ->color(fn ($state) => match ($state) {
                        TenantType::Transporter->value => 'warning',
                        TenantType::Stable->value => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state
                        ? (TenantType::tryFrom((string) $state)?->label() ?? (string) $state)
                        : '—'),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    // Wizualny sygnał że plan jest legacy — admin powinien
                    // przenieść jego tenantów na nową taryfę.
                    ->badge(fn (Plan $r): bool => str_ends_with((string) $r->code, '_legacy'))
                    ->color(fn (Plan $r): ?string => str_ends_with((string) $r->code, '_legacy') ? 'danger' : null),
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
                Tables\Columns\TextColumn::make('active_tenants_count')
                    ->label(__('admin/plan.table.column.active_tenants_count'))
                    ->counts([
                        'tenants as active_tenants_count' => fn ($q) => $q->whereIn('status', ['trialing', 'active']),
                    ])
                    ->numeric(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('admin/plan.table.column.is_active_short')),
                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label(__('admin/plan.table.column.is_public_short')),
            ])
            // Stables grupowane nad transporterami; w obrębie audience po sort_order.
            ->modifyQueryUsing(fn ($query) => $query->orderBy('audience')->orderBy('sort_order'))
            ->filters([
                Tables\Filters\SelectFilter::make('audience')
                    ->label(__('admin/plan.table.filter.audience'))
                    ->options(TenantType::options()),
            ])
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

    /**
     * Czy form w danym stanie reprezentuje plan Enterprise (custom pricing).
     * Używamy do ukrycia sekcji multi-currency — Enterprise nie ma fixed cen.
     */
    private static function isEnterprisePlan(Forms\Get $get): bool
    {
        $features = $get('features');
        if (is_array($features) && (! empty($features['is_custom_pricing'])
            || ($features['marketing_cta'] ?? null) === 'contact_sales')) {
            return true;
        }

        $monthly = (int) ($get('price_monthly_cents') ?? 0);
        $yearly = (int) ($get('price_yearly_cents') ?? 0);

        return $monthly <= 0 && $yearly <= 0;
    }

    /**
     * @return array<string, string>
     */
    private static function currencyOptionsForOverlay(): array
    {
        // Pokazujemy wszystkie wspierane waluty — UI nie wie jaka jest
        // baza w momencie renderowania repeatera (form ma własny state).
        // Walidacja "nie duplikuj base'a" jest deklarowana w helper text.
        $opts = [];
        foreach (Plan::supportedCurrencies() as $c) {
            $opts[$c] = $c;
        }

        return $opts;
    }
}
