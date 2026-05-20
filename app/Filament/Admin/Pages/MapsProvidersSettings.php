<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\SystemSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Master-admin: konfiguracja API kluczy dla map / routing / geocoding.
 *
 * 3 providery używane przez moduł transport:
 *   - Mapbox (`transport.mapbox.token`)
 *     — geocoding adresów + routing dla tańszego provider'a (MapboxGeocoder,
 *       MapboxProvider). Hovera używa go domyślnie w PL na MVP.
 *   - OpenRouteService ORS (`transport.ors.api_key`)
 *     — alternative routing provider, open-source backend, free tier (2000
 *       req/day). Fallback przy out-of-quota Mapbox.
 *   - Google Maps Routes API (`transport.google.api_key`)
 *     — premium routing (lepsze ETA w godzinach szczytu, real traffic).
 *       Plan-aware: tylko plany Business+ tenants mogą wybrać.
 *
 * Provider klasy (MapboxGeocoder/MapboxProvider/GoogleMapsProvider/
 * OpenRouteServiceProvider) czytają tokeny w kolejności:
 *   1. SystemSetting (te ustawienia tu) — pierwszeństwo
 *   2. config('transport.providers.*') = .env — fallback
 *
 * To pozwala master adminowi rotować klucze bez SSH do .env.
 */
class MapsProvidersSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    public static function getNavigationLabel(): string
    {
        return __('admin/maps_providers.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/maps_providers.title');
    }

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.admin.pages.maps-providers-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mapbox_token' => SystemSetting::getSecret('transport.mapbox.token', '') ?? '',
            'ors_api_key' => SystemSetting::getSecret('transport.ors.api_key', '') ?? '',
            'google_api_key' => SystemSetting::getSecret('transport.google.api_key', '') ?? '',
            'autocomplete_provider_panel' => SystemSetting::getValue('transport.autocomplete.provider_panel', 'mapbox'),
            'autocomplete_provider_public' => SystemSetting::getValue('transport.autocomplete.provider_public', 'photon'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('admin/maps_providers.form.section.mapbox'))
                    ->description(__('admin/maps_providers.form.section.mapbox_description'))
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\TextInput::make('mapbox_token')
                            ->label(__('admin/maps_providers.form.label.mapbox_token'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin/maps_providers.form.helper.mapbox_token'))
                            ->placeholder('pk.eyJ1Ijoi...'),
                    ]),

                Forms\Components\Section::make(__('admin/maps_providers.form.section.ors'))
                    ->description(__('admin/maps_providers.form.section.ors_description'))
                    ->icon('heroicon-o-globe-europe-africa')
                    ->schema([
                        Forms\Components\TextInput::make('ors_api_key')
                            ->label(__('admin/maps_providers.form.label.ors_api_key'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin/maps_providers.form.helper.ors_api_key'))
                            ->placeholder('5b3ce3597851110001cf6...'),
                    ]),

                Forms\Components\Section::make(__('admin/maps_providers.form.section.google'))
                    ->description(__('admin/maps_providers.form.section.google_description'))
                    ->icon('heroicon-o-globe-alt')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('google_api_key')
                            ->label(__('admin/maps_providers.form.label.google_api_key'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin/maps_providers.form.helper.google_api_key'))
                            ->placeholder('AIzaSy...'),
                    ]),

                Forms\Components\Section::make(__('admin/maps_providers.form.section.autocomplete'))
                    ->description(__('admin/maps_providers.form.section.autocomplete_description'))
                    ->icon('heroicon-o-magnifying-glass')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('autocomplete_provider_panel')
                            ->label(__('admin/maps_providers.form.label.autocomplete_provider_panel'))
                            ->helperText(__('admin/maps_providers.form.helper.autocomplete_provider_panel'))
                            ->options([
                                'off' => __('admin/maps_providers.form.option.autocomplete.off'),
                                'photon' => __('admin/maps_providers.form.option.autocomplete.photon'),
                                'mapbox' => __('admin/maps_providers.form.option.autocomplete.mapbox'),
                            ])
                            ->default('mapbox')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('autocomplete_provider_public')
                            ->label(__('admin/maps_providers.form.label.autocomplete_provider_public'))
                            ->helperText(__('admin/maps_providers.form.helper.autocomplete_provider_public'))
                            ->options([
                                'off' => __('admin/maps_providers.form.option.autocomplete.off'),
                                'photon' => __('admin/maps_providers.form.option.autocomplete.photon'),
                                'mapbox' => __('admin/maps_providers.form.option.autocomplete.mapbox'),
                            ])
                            ->default('photon')
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    public function save(): void
    {
        $form = $this->form->getState();

        // Pusty input → NIE nadpisujemy (master admin może zostawić puste żeby
        // nie zmieniać klucza). Tylko niepuste wartości trafiają do SystemSetting.
        $mapbox = trim((string) ($form['mapbox_token'] ?? ''));
        if ($mapbox !== '') {
            SystemSetting::setSecret('transport.mapbox.token', $mapbox);
        }

        $ors = trim((string) ($form['ors_api_key'] ?? ''));
        if ($ors !== '') {
            SystemSetting::setSecret('transport.ors.api_key', $ors);
        }

        $google = trim((string) ($form['google_api_key'] ?? ''));
        if ($google !== '') {
            SystemSetting::setSecret('transport.google.api_key', $google);
        }

        foreach (['panel', 'public'] as $context) {
            $key = "autocomplete_provider_{$context}";
            $value = (string) ($form[$key] ?? '');
            if (in_array($value, ['off', 'photon', 'mapbox'], true)) {
                SystemSetting::setValue("transport.autocomplete.provider_{$context}", $value);
            }
        }

        Notification::make()
            ->title(__('admin/maps_providers.action.saved'))
            ->success()
            ->send();
    }
}
