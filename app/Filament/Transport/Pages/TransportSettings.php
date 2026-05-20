<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\Ksef\TransporterKsefService;
use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Domain\Transport\Routing\RoutingProviderProbe;
use App\Enums\FuelCalculationMode;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\TransportSettings as TransportSettingsModel;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Singleton — konfiguracja stawek transportowych. Patrz docs/TRANSPORT.md §4.4.
 *
 * Wzorzec analogiczny do InvoicingSettings (Filament Page + form() + save())
 * z tą różnicą, że dane idą do osobnej tabeli `transport_settings` w bazie
 * tenant'a (singleton row), nie do `tenants.settings` JSON.
 */
class TransportSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getNavigationLabel(): string
    {
        return __('transport/settings.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/settings.title');
    }

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.transport.pages.transport-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $settings = TransportSettingsModel::current();
        $routing = (array) ($settings->routing_provider ?? ['provider' => 'ors']);

        $tenant = app(TenantManager::class)->current();

        $this->form->fill([
            'rate_per_km' => $settings->rate_per_km,
            'rate_per_km_loaded' => $settings->rate_per_km_loaded,
            'minimum_charge' => $settings->minimum_charge,
            'extra_horse_fee_default' => $settings->extra_horse_fee_default,
            'fixed_fees_default' => $settings->fixed_fees_default ?? [],
            'surcharge_percent_default' => $settings->surcharge_percent_default,
            'fuel_consumption_l_per_100km' => $settings->fuel_consumption_l_per_100km,
            'fuel_surcharge_enabled' => $settings->fuel_surcharge_enabled,
            'fuel_calculation_mode' => $settings->fuel_calculation_mode ?? 'surcharge',
            'fuel_base_price_pln' => $settings->fuel_base_price_pln,
            'vat_rate' => $settings->vat_rate,
            'currency' => $settings->currency,
            'routing_provider' => $routing['provider'] ?? 'ors',
            'routing_api_key' => $routing['api_key'] ?? null,
            // KSeF: token NIGDY nie wraca do UI w czystej formie. Pokazujemy
            // tylko czy jest ustawiony — wartość pozostawiamy pustą; jeśli
            // user nic nie wpisze przy save, zachowujemy istniejący token.
            'ksef_nip' => $settings->ksef_nip ?? ($tenant?->tax_id),
            'ksef_environment' => $settings->ksef_environment ?? 'test',
            'ksef_token' => null,
            'ksef_token_present' => $settings->getKsefToken() !== null,
            'ksef_enabled' => (bool) $settings->ksef_enabled,
            'default_payment_url_template' => $settings->default_payment_url_template,
            'default_payment_method_label' => $settings->default_payment_method_label,
            'payment_instructions' => $settings->payment_instructions,
            'p24_quote_autopay_enabled' => (bool) $settings->p24_quote_autopay_enabled,
            'payu_quote_autopay_enabled' => (bool) ($settings->payu_quote_autopay_enabled ?? false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('transport/settings.section.rates'))
                    ->description(__('transport/settings.section.rates_description'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('rate_per_km')
                            ->label(__('transport/settings.form.label.rate_per_km'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('rate_per_km_loaded')
                            ->label(__('transport/settings.form.label.rate_per_km_loaded'))
                            ->helperText(__('transport/settings.form.helper.rate_per_km_loaded'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('minimum_charge')
                            ->label(__('transport/settings.form.label.minimum_charge'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('extra_horse_fee_default')
                            ->label(__('transport/settings.form.label.extra_horse_fee_default'))
                            ->helperText(__('transport/settings.form.helper.extra_horse_fee_default'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0),
                        Forms\Components\TextInput::make('surcharge_percent_default')
                            ->label(__('transport/settings.form.label.surcharge_percent_default'))
                            ->helperText(__('transport/settings.form.helper.surcharge_percent_default'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(500)
                            ->step(0.01)
                            ->suffix('%'),
                        Forms\Components\Repeater::make('fixed_fees_default')
                            ->label(__('transport/settings.form.label.fixed_fees_default'))
                            ->helperText(__('transport/settings.form.helper.fixed_fees_default'))
                            ->columnSpanFull()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('transport/settings.form.label.fixed_fees_name'))
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('amount')
                                    ->label(__('transport/settings.form.label.fixed_fees_amount'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01),
                            ])
                            ->columns(2)
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->addActionLabel(__('transport/settings.form.action.add_fixed_fee')),
                    ]),

                Forms\Components\Section::make(__('transport/settings.section.fuel'))
                    ->description(__('transport/settings.section.fuel_description'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('fuel_consumption_l_per_100km')
                            ->label(__('transport/settings.form.label.fuel_consumption_l_per_100km'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.1)
                            ->suffix('L'),
                        Forms\Components\Toggle::make('fuel_surcharge_enabled')
                            ->label(__('transport/settings.form.label.fuel_surcharge_enabled'))
                            ->helperText(__('transport/settings.form.helper.fuel_surcharge_enabled'))
                            ->inline(false)
                            ->live(),
                        Forms\Components\Select::make('fuel_calculation_mode')
                            ->label(__('transport/settings.form.label.fuel_calculation_mode'))
                            ->helperText(__('transport/settings.form.helper.fuel_calculation_mode'))
                            ->options(FuelCalculationMode::options())
                            ->default(FuelCalculationMode::Surcharge->value)
                            ->native(false)
                            ->required()
                            ->live()
                            ->visible(fn (Forms\Get $get) => (bool) $get('fuel_surcharge_enabled')),
                        Forms\Components\TextInput::make('fuel_base_price_pln')
                            ->label(__('transport/settings.form.label.fuel_base_price_pln'))
                            ->helperText(__('transport/settings.form.helper.fuel_base_price_pln'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('PLN/L')
                            ->visible(fn (Forms\Get $get) => (bool) $get('fuel_surcharge_enabled')
                                && $get('fuel_calculation_mode') === FuelCalculationMode::Surcharge->value),
                    ]),

                Forms\Components\Section::make(__('transport/settings.section.tax_currency'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('vat_rate')
                            ->label(__('transport/settings.form.label.vat_rate'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->step(0.01)
                            ->suffix('%'),
                        Forms\Components\Select::make('currency')
                            ->label(__('transport/settings.form.label.currency'))
                            ->required()
                            ->options([
                                'PLN' => 'PLN — złoty polski',
                                'EUR' => 'EUR — euro',
                                'CZK' => 'CZK — koruna czeska',
                            ])
                            ->default('PLN')
                            ->native(false),
                    ]),

                Forms\Components\Section::make(__('transport/settings.section.routing'))
                    ->description(__('transport/settings.section.routing_description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('routing_provider')
                            ->label(__('transport/settings.form.label.routing_provider'))
                            ->required()
                            ->options([
                                'ors' => __('transport/settings.form.option.routing_provider.ors'),
                                'mapbox' => __('transport/settings.form.option.routing_provider.mapbox'),
                                'google' => __('transport/settings.form.option.routing_provider.google'),
                            ])
                            ->default('ors')
                            ->native(false),
                        Forms\Components\TextInput::make('routing_api_key')
                            ->label(__('transport/settings.form.label.routing_api_key'))
                            ->helperText(__('transport/settings.form.helper.routing_api_key'))
                            ->password()
                            ->revealable(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('testApiKey')
                                ->label(__('transport/api_config.action.test_key'))
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action(function (Forms\Get $get) {
                                    $this->testApiKey(
                                        (string) ($get('routing_provider') ?? ''),
                                        (string) ($get('routing_api_key') ?? ''),
                                    );
                                }),
                        ])->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('transport/ksef.section.title'))
                    ->description(__('transport/ksef.section.description'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('ksef_disclaimer')
                            ->columnSpanFull()
                            ->label('')
                            ->content(__('transport/ksef.section.disclaimer')),

                        Forms\Components\TextInput::make('ksef_nip')
                            ->label(__('transport/ksef.form.label.nip'))
                            ->helperText(__('transport/ksef.form.helper.nip'))
                            ->regex('/^\d{10}$/')
                            ->maxLength(16),

                        Forms\Components\Select::make('ksef_environment')
                            ->label(__('transport/ksef.form.label.environment'))
                            ->options([
                                'test' => __('transport/ksef.form.option.environment.test'),
                                'demo' => __('transport/ksef.form.option.environment.demo'),
                                'production' => __('transport/ksef.form.option.environment.production'),
                            ])
                            ->default('test')
                            ->required()
                            ->native(false),

                        Forms\Components\Hidden::make('ksef_token_present')
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('ksef_token')
                            ->label(__('transport/ksef.form.label.token'))
                            ->helperText(fn (Forms\Get $get) => $get('ksef_token_present')
                                ? __('transport/ksef.form.helper.token_set')
                                : __('transport/ksef.form.helper.token_empty'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('ksef_enabled')
                            ->label(__('transport/ksef.form.label.enabled'))
                            ->helperText(__('transport/ksef.form.helper.enabled'))
                            ->inline(false)
                            ->columnSpanFull(),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('testKsefConnection')
                                ->label(__('transport/ksef.action.test_connection'))
                                ->icon('heroicon-o-signal')
                                ->color('info')
                                ->action(fn () => $this->testKsefConnection()),
                        ])->columnSpanFull(),
                    ]),

                // Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
                // Hovera NIE pośredniczy w płatnościach — to tylko ułatwienie
                // dla transportera (paste-and-go URL bramki).
                Forms\Components\Section::make(__('transport/settings.section.payments'))
                    ->description(__('transport/settings.section.payments_description'))
                    ->schema([
                        Forms\Components\Placeholder::make('payments_disclaimer')
                            ->label('')
                            ->content(__('transport/settings.section.payments_disclaimer'))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('default_payment_url_template')
                            ->label(__('transport/settings.form.label.default_payment_url_template'))
                            ->helperText(__('transport/settings.form.helper.default_payment_url_template'))
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('https://buy.stripe.com/...?prefilled_email={customer_name}'),
                        Forms\Components\TextInput::make('default_payment_method_label')
                            ->label(__('transport/settings.form.label.default_payment_method_label'))
                            ->helperText(__('transport/settings.form.helper.default_payment_method_label'))
                            ->maxLength(80),
                        Forms\Components\Textarea::make('payment_instructions')
                            ->label(__('transport/settings.form.label.payment_instructions'))
                            ->helperText(__('transport/settings.form.helper.payment_instructions'))
                            ->rows(4),
                    ]),

                // Stripe Connect Express — direct charge per transporter.
                // Patrz docs/TRANSPORT.md §15.6.
                Forms\Components\Section::make(__('transport/stripe_connect.section.title'))
                    ->description(__('transport/stripe_connect.section.description'))
                    ->schema([
                        Forms\Components\Placeholder::make('stripe_connect_disclaimer')
                            ->label('')
                            ->content(__('transport/stripe_connect.section.disclaimer'))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('stripe_connect_status_badge')
                            ->label(__('transport/stripe_connect.form.label.status'))
                            ->content(fn () => $this->renderStripeConnectStatusBadge())
                            ->columnSpanFull(),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('stripeConnectOnboard')
                                ->label(__('transport/stripe_connect.action.connect'))
                                ->icon('heroicon-o-link')
                                ->color('primary')
                                ->visible(fn () => in_array($this->stripeConnectStatus(), ['none', 'restricted', 'rejected'], true))
                                ->url('/transport/stripe/connect/onboard'),

                            Forms\Components\Actions\Action::make('stripeConnectRefresh')
                                ->label(__('transport/stripe_connect.action.refresh_status'))
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->visible(fn () => $this->stripeConnectStatus() !== 'none')
                                ->action(fn () => $this->refreshStripeConnectStatus()),

                            Forms\Components\Actions\Action::make('stripeConnectDashboard')
                                ->label(__('transport/stripe_connect.action.open_dashboard'))
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('info')
                                ->visible(fn () => $this->stripeConnectStatus() === 'enabled')
                                ->url('/transport/stripe/connect/dashboard')
                                ->openUrlInNewTab(),
                        ])->columnSpanFull(),
                    ]),

                // P24 quote autopay — patrz docs/TRANSPORT.md §15.5.
                // Hovera tylko technicznie odpala P24 register z creds
                // transportera — pieniądze idą bezpośrednio na konto P24
                // transportera. Hovera nie pośredniczy. Sam credential
                // żyje w /app/payment-settings (PaymentSettings page) —
                // single source of truth.
                Forms\Components\Section::make(__('transport/p24.section.title'))
                    ->description(__('transport/p24.section.description'))
                    ->schema([
                        Forms\Components\Placeholder::make('p24_disclaimer')
                            ->label('')
                            ->content(__('transport/p24.section.disclaimer'))
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('p24_quote_autopay_enabled')
                            ->label(__('transport/p24.form.label.autopay_enabled'))
                            ->helperText(__('transport/p24.form.helper.autopay_enabled'))
                            ->inline(false),

                        Forms\Components\Placeholder::make('p24_creds_pointer')
                            ->label('')
                            ->content(__('transport/p24.form.helper.credentials_pointer'))
                            ->columnSpanFull(),
                    ]),

                // PayU quote autopay — patrz docs/TRANSPORT.md §16.
                // Hovera tylko technicznie odpala PayU order create z creds
                // transportera — pieniądze idą bezpośrednio na konto PayU
                // transportera. Marketplace passthrough — Hovera nie pośredniczy.
                // Credentials żyją w /app/payment-settings (tenants.settings.payments.payu).
                Forms\Components\Section::make(__('transport/payu.section.title'))
                    ->description(__('transport/payu.section.description'))
                    ->schema([
                        Forms\Components\Placeholder::make('payu_disclaimer')
                            ->label('')
                            ->content(__('transport/payu.section.disclaimer'))
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('payu_quote_autopay_enabled')
                            ->label(__('transport/payu.form.label.autopay_enabled'))
                            ->helperText(__('transport/payu.form.helper.autopay_enabled'))
                            ->inline(false),

                        Forms\Components\Placeholder::make('payu_creds_pointer')
                            ->label('')
                            ->content(__('transport/payu.form.helper.credentials_pointer'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Status Stripe Connect z aktualnego tenant'a (central DB). Read-only
     * w form'cie — modyfikowany przez StripeConnectController + webhooki.
     */
    private function stripeConnectStatus(): string
    {
        $tenant = app(TenantManager::class)->current();

        return (string) ($tenant?->stripe_connect_status ?? 'none');
    }

    private function renderStripeConnectStatusBadge(): Htmlable
    {
        $status = $this->stripeConnectStatus();
        $color = match ($status) {
            'enabled' => 'success',
            'pending' => 'warning',
            'restricted' => 'danger',
            'rejected' => 'danger',
            default => 'gray',
        };
        $label = __("transport/stripe_connect.status.{$status}");

        // Filament's `Badge` blade nie jest tu dostępny w placeholderze —
        // wstrzykujemy minimalny HTML zgodny z tailwind palette stripe_connect.
        $palette = match ($color) {
            'success' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'danger' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-300',
        };

        return new HtmlString(
            '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium '.e($palette).'">'
            .e($label).'</span>'
        );
    }

    /**
     * Wywołane przyciskiem „Sprawdź status" — robi POST na endpoint
     * StripeConnectController::refresh przez Filament action. Idempotentne.
     */
    public function refreshStripeConnectStatus(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->current();
        if ($tenant === null || ! $tenant->isTransporter()) {
            return;
        }

        try {
            app(TransporterStripeConnectService::class)
                ->syncAccountStatus($tenant);

            Notification::make()
                ->success()
                ->title(__('transport/stripe_connect.notify.refreshed'))
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('transport/stripe_connect.notify.status_sync_failed'))
                ->body($e->getMessage())
                ->send();
        }
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $form = $this->form->getState();
        $settings = TransportSettingsModel::current();

        $settings->forceFill([
            'rate_per_km' => (float) $form['rate_per_km'],
            'rate_per_km_loaded' => $form['rate_per_km_loaded'] !== null && $form['rate_per_km_loaded'] !== ''
                ? (float) $form['rate_per_km_loaded']
                : null,
            'minimum_charge' => (float) $form['minimum_charge'],
            'extra_horse_fee_default' => (float) ($form['extra_horse_fee_default'] ?? 0),
            'fixed_fees_default' => is_array($form['fixed_fees_default'] ?? null)
                ? array_values($form['fixed_fees_default'])
                : [],
            'surcharge_percent_default' => isset($form['surcharge_percent_default']) && $form['surcharge_percent_default'] !== ''
                ? (float) $form['surcharge_percent_default']
                : null,
            'fuel_consumption_l_per_100km' => (float) $form['fuel_consumption_l_per_100km'],
            'fuel_surcharge_enabled' => (bool) $form['fuel_surcharge_enabled'],
            'fuel_calculation_mode' => in_array(($form['fuel_calculation_mode'] ?? 'surcharge'), ['surcharge', 'full_cost'], true)
                ? $form['fuel_calculation_mode']
                : 'surcharge',
            'fuel_base_price_pln' => (float) ($form['fuel_base_price_pln'] ?? 7.00),
            'vat_rate' => (float) $form['vat_rate'],
            'currency' => (string) $form['currency'],
            'routing_provider' => array_filter([
                'provider' => (string) $form['routing_provider'],
                'api_key' => $form['routing_api_key'] ?: null,
            ]),
            'ksef_nip' => $form['ksef_nip'] ?: null,
            'ksef_environment' => (string) ($form['ksef_environment'] ?? 'test'),
            'ksef_enabled' => (bool) ($form['ksef_enabled'] ?? false),
            'default_payment_url_template' => $form['default_payment_url_template'] ?: null,
            'default_payment_method_label' => $form['default_payment_method_label'] ?: null,
            'payment_instructions' => $form['payment_instructions'] ?: null,
            'p24_quote_autopay_enabled' => (bool) ($form['p24_quote_autopay_enabled'] ?? false),
            'payu_quote_autopay_enabled' => (bool) ($form['payu_quote_autopay_enabled'] ?? false),
        ]);

        // Token: jeśli user wpisał nowy → szyfrujemy i zapisujemy. Jeśli
        // zostawił puste → zachowujemy istniejący (UI nigdy nie pokazuje
        // czystej wartości, więc puste = "nie ruszać", nie "kasuj").
        $newToken = (string) ($form['ksef_token'] ?? '');
        if ($newToken !== '') {
            $settings->setKsefToken($newToken);
        }

        // Defensive: nie pozwalamy włączyć KSeF bez tokenu (UX safety net).
        if ($settings->getKsefToken() === null) {
            $settings->ksef_enabled = false;
        }

        $settings->save();

        app(TenantAuditLogger::class)->record(
            'transport.settings_updated',
            'TransportSettings',
            (string) $settings->id,
        );

        Notification::make()
            ->title(__('transport/settings.action.saved'))
            ->success()
            ->send();
    }

    /**
     * Test ważności klucza API providera routingu. Wywołuje minimalny call
     * przez RoutingProviderProbe i pokazuje notification z wynikiem. Pomaga
     * userowi sprawdzić czy wkleił prawidłowy token zanim wystawi pierwszą
     * realną ofertę.
     */
    public function testApiKey(string $providerId, string $apiKey): void
    {
        abort_unless(self::canAccess(), 403);

        $result = app(RoutingProviderProbe::class)->test($providerId, $apiKey);

        if ($result['success']) {
            Notification::make()
                ->success()
                ->title(__('transport/api_config.notify.success'))
                ->body($result['message'])
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title(__('transport/api_config.notify.failure'))
            ->body($result['message'])
            ->persistent()
            ->send();
    }

    /**
     * Akcja „Test connection" w sekcji KSeF — wywołuje najtańszy
     * auth-only endpoint MF (Session/Status), tylko po to żeby
     * potwierdzić że token + środowisko są kompatybilne.
     *
     * NIE zapisuje konfiguracji — to oddzielne wywołanie save().
     */
    public function testKsefConnection(): void
    {
        abort_unless(self::canAccess(), 403);

        $result = app(TransporterKsefService::class)->testConnection();

        if ($result['success']) {
            Notification::make()
                ->success()
                ->title(__('transport/ksef.notify.test_ok'))
                ->body($result['message'])
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title(__('transport/ksef.notify.test_failed'))
            ->body($result['message'])
            ->persistent()
            ->send();
    }
}
