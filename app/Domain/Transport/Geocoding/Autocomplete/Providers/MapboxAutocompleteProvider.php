<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Autocomplete\Providers;

use App\Domain\Transport\Geocoding\Autocomplete\Contracts\PlacesAutocompleteProvider;
use App\Domain\Transport\Geocoding\Autocomplete\Suggestion;
use App\Models\Central\SystemSetting;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Mapbox geocoding API — payable z dobrym pokryciem PL adresów (z numerami
 * domów). Endpoint:
 *
 *   GET https://api.mapbox.com/geocoding/v5/mapbox.places/{q}.json
 *
 * Klucz czytany z SystemSetting `transport.mapbox.token` (ten sam, który
 * MapboxGeocoder używa do server-side geocodingu w calculate()).
 *
 * Proxy-style: token NIE jest eksponowany do przeglądarki — JS bije w nasz
 * `/api/transport/places/suggest`, controller woła Mapbox z tokenem z systemu.
 */
final class MapboxAutocompleteProvider implements PlacesAutocompleteProvider
{
    private const BASE_URL = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

    private readonly string $accessToken;

    public function __construct(
        private readonly HttpFactory $http,
        ?string $accessToken = null,
        private readonly int $timeoutSeconds = 5,
    ) {
        $this->accessToken = $accessToken
            ?? SystemSetting::getSecret('transport.mapbox.token')
            ?? (string) config('transport.providers.mapbox.access_token', '');
    }

    public function id(): string
    {
        return 'mapbox';
    }

    public function isAvailable(): bool
    {
        return $this->accessToken !== '';
    }

    public function suggest(string $query, string $countryCode = 'pl', int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->isAvailable()) {
            return [];
        }

        $url = self::BASE_URL.'/'.rawurlencode($query).'.json';
        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->get($url, [
                    'access_token' => $this->accessToken,
                    'country' => $countryCode,
                    'autocomplete' => 'true',
                    'limit' => max(1, min(10, $limit)),
                    'language' => 'pl',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Mapbox autocomplete request failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $features = (array) $response->json('features', []);
        $out = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $center = (array) ($feature['center'] ?? []);
            if (count($center) < 2) {
                continue;
            }
            $label = (string) ($feature['place_name'] ?? '');
            if ($label === '') {
                continue;
            }
            $out[] = new Suggestion(
                label: $label,
                lat: (float) $center[1],
                lng: (float) $center[0],
                providerId: $this->id(),
            );
        }

        return $out;
    }
}
