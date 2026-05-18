<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\Routing\RoutingProviderProbe;
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

        $this->form->fill([
            'rate_per_km' => $settings->rate_per_km,
            'rate_per_km_loaded' => $settings->rate_per_km_loaded,
            'minimum_charge' => $settings->minimum_charge,
            'fuel_consumption_l_per_100km' => $settings->fuel_consumption_l_per_100km,
            'fuel_surcharge_enabled' => $settings->fuel_surcharge_enabled,
            'fuel_base_price_pln' => $settings->fuel_base_price_pln,
            'vat_rate' => $settings->vat_rate,
            'currency' => $settings->currency,
            'routing_provider' => $routing['provider'] ?? 'ors',
            'routing_api_key' => $routing['api_key'] ?? null,
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
                            ->inline(false),
                        Forms\Components\TextInput::make('fuel_base_price_pln')
                            ->label(__('transport/settings.form.label.fuel_base_price_pln'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('PLN/L')
                            ->visible(fn (Forms\Get $get) => (bool) $get('fuel_surcharge_enabled')),
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
            ]);
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
            'fuel_consumption_l_per_100km' => (float) $form['fuel_consumption_l_per_100km'],
            'fuel_surcharge_enabled' => (bool) $form['fuel_surcharge_enabled'],
            'fuel_base_price_pln' => (float) ($form['fuel_base_price_pln'] ?? 7.00),
            'vat_rate' => (float) $form['vat_rate'],
            'currency' => (string) $form['currency'],
            'routing_provider' => array_filter([
                'provider' => (string) $form['routing_provider'],
                'api_key' => $form['routing_api_key'] ?: null,
            ]),
        ])->save();

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
}
