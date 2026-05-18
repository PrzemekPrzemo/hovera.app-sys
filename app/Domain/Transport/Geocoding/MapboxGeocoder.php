<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding;

use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Routing\Data\Coords;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Forward geocoding przez Mapbox API. Używamy tego samego klucza co
 * Mapbox routing (TRANSPORT_MAPBOX_TOKEN). Dla MVP wystarczy jeden
 * provider — Nominatim/OSM jako fallback przyjdzie gdy klient zażąda
 * routing-providera bez Mapbox kluczy.
 *
 * Endpoint:
 *   GET https://api.mapbox.com/geocoding/v5/mapbox.places/{query}.json
 *   ?access_token=...&country=pl&limit=1
 */
class MapboxGeocoder
{
    private const BASE_URL = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

    private string $accessToken;

    private int $timeoutSeconds;

    public function __construct(
        private readonly HttpFactory $http,
        ?string $accessToken = null,
        ?int $timeoutSeconds = null,
    ) {
        $this->accessToken = $accessToken ?? (string) config('transport.providers.mapbox.access_token', '');
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('transport.providers.mapbox.timeout', 15);
    }

    public function geocode(string $query, string $countryCode = 'pl'): GeocodedAddress
    {
        if ($this->accessToken === '') {
            throw GeocodingException::missingCredentials('mapbox');
        }

        $query = trim($query);
        if ($query === '') {
            throw GeocodingException::notFound('(empty)');
        }

        $url = self::BASE_URL.'/'.rawurlencode($query).'.json';
        $response = $this->http
            ->timeout($this->timeoutSeconds)
            ->get($url, [
                'access_token' => $this->accessToken,
                'country' => $countryCode,
                'limit' => 1,
                'language' => 'pl',
            ]);

        if (! $response->successful()) {
            throw GeocodingException::apiError('mapbox', $response->status());
        }

        $feature = $response->json('features.0');
        if (! is_array($feature) || ! isset($feature['center']) || ! is_array($feature['center'])) {
            throw GeocodingException::notFound($query);
        }

        [$lng, $lat] = $feature['center'];

        return new GeocodedAddress(
            displayName: (string) ($feature['place_name'] ?? $query),
            coords: new Coords((float) $lat, (float) $lng),
            countryCode: $countryCode,
            voivodeship: $this->extractVoivodeship($feature),
        );
    }

    /**
     * Mapbox feature ma `context` array — region (voivodeship) jest jednym
     * z elementów z `id` zaczynającym się od "region.". Dla PL Mapbox zwraca
     * nazwę polską (np. "Mazowieckie"). Lowercase'ujemy żeby pasowało do
     * config('transport.voivodeship_adjacency') keys i transport_service_areas.
     */
    private function extractVoivodeship(array $feature): ?string
    {
        $context = $feature['context'] ?? [];
        if (! is_array($context)) {
            return null;
        }

        foreach ($context as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if (str_starts_with($id, 'region.')) {
                $text = (string) ($entry['text_pl'] ?? $entry['text'] ?? '');

                return $text !== '' ? mb_strtolower($text) : null;
            }
        }

        return null;
    }
}
