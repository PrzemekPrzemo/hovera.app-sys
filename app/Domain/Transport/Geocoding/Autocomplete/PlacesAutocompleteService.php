<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Autocomplete;

use App\Domain\Transport\Geocoding\Autocomplete\Contracts\PlacesAutocompleteProvider;
use App\Domain\Transport\Geocoding\Autocomplete\Providers\MapboxAutocompleteProvider;
use App\Domain\Transport\Geocoding\Autocomplete\Providers\PhotonProvider;
use App\Models\Central\SystemSetting;

/**
 * Wybiera provider autocomplete'u na podstawie kontekstu (panel / public)
 * i SystemSetting konfiguracji master admina.
 *
 * Klucze konfiguracyjne (admin UI: /admin/maps-providers-settings):
 *   - transport.autocomplete.provider_panel  : 'off' | 'photon' | 'mapbox'  (default 'mapbox')
 *   - transport.autocomplete.provider_public : 'off' | 'photon' | 'mapbox'  (default 'photon')
 *
 * Per-context bo zwykle:
 *   - panel  = transporter się loguje, payable Mapbox jest OK (lepsze adresy)
 *   - public = anonymous traffic, free Photon żeby nie palić Mapbox kwoty
 *     na boty/probe'y.
 *
 * Master admin może obie pozycje przestawić na 'off' żeby całkowicie wyłączyć
 * autocomplete (back-compat z plain TextInput).
 */
final class PlacesAutocompleteService
{
    public function __construct(
        private readonly PhotonProvider $photon,
        private readonly MapboxAutocompleteProvider $mapbox,
    ) {}

    public function isEnabledFor(string $context): bool
    {
        return $this->providerNameFor($context) !== 'off';
    }

    /**
     * @return list<Suggestion>
     */
    public function suggest(string $context, string $query, string $countryCode = 'pl', int $limit = 5): array
    {
        $provider = $this->resolveProvider($context);
        if ($provider === null) {
            return [];
        }

        return $provider->suggest($query, $countryCode, $limit);
    }

    public function resolveProvider(string $context): ?PlacesAutocompleteProvider
    {
        $name = $this->providerNameFor($context);

        $provider = match ($name) {
            'mapbox' => $this->mapbox,
            'photon' => $this->photon,
            default => null,
        };

        if ($provider === null) {
            return null;
        }

        // Soft fallback: jeśli admin wybrał Mapbox ale token nie wgrany, niech
        // autocomplete jakkolwiek działa via Photon zamiast wisieć w UI.
        if ($name === 'mapbox' && ! $provider->isAvailable()) {
            return $this->photon;
        }

        return $provider;
    }

    public function providerNameFor(string $context): string
    {
        $key = $context === 'public'
            ? 'transport.autocomplete.provider_public'
            : 'transport.autocomplete.provider_panel';
        $default = $context === 'public' ? 'photon' : 'mapbox';

        $value = SystemSetting::getValue($key, $default);

        return in_array($value, ['off', 'photon', 'mapbox'], true) ? (string) $value : $default;
    }
}
