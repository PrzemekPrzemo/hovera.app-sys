<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Autocomplete\Providers;

use App\Domain\Transport\Geocoding\Autocomplete\Contracts\PlacesAutocompleteProvider;
use App\Domain\Transport\Geocoding\Autocomplete\Suggestion;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Photon (komoot.io) — darmowy OSM-based geocoder.
 *
 *   https://photon.komoot.io/api/?q=warszawa&lang=pl&limit=5
 *
 * Plus: brak tokenów, brak limitu kwoty.
 * Minus: mniejsza dokładność dla numerów domów PL niż Mapbox, free tier
 *        nie gwarantuje SLA.
 *
 * Używamy jako default (gdy admin nie wybrał providera) bo działa
 * out-of-the-box bez konfiguracji.
 */
final class PhotonProvider implements PlacesAutocompleteProvider
{
    private const BASE_URL = 'https://photon.komoot.io/api/';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly int $timeoutSeconds = 5,
    ) {}

    public function id(): string
    {
        return 'photon';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function suggest(string $query, string $countryCode = 'pl', int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->get(self::BASE_URL, [
                    'q' => $query,
                    'lang' => $countryCode === 'pl' ? 'pl' : 'en',
                    'limit' => max(1, min(10, $limit)),
                    // Photon nie ma per-country filtra, ale 'lang' biasuje wyniki.
                ]);
        } catch (\Throwable $e) {
            Log::warning('Photon autocomplete request failed', ['error' => $e->getMessage()]);

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
            $props = (array) ($feature['properties'] ?? []);
            $geom = (array) ($feature['geometry'] ?? []);
            $coords = (array) ($geom['coordinates'] ?? []);
            if (count($coords) < 2) {
                continue;
            }
            $label = $this->formatLabel($props);
            if ($label === '') {
                continue;
            }
            $out[] = new Suggestion(
                label: $label,
                lat: (float) $coords[1],
                lng: (float) $coords[0],
                providerId: $this->id(),
            );
        }

        return $out;
    }

    /**
     * Photon properties: name, street, housenumber, postcode, city,
     * district, state, country. Składamy w postaci podobnej do Mapbox
     * place_name: "ul. Marszałkowska 1, 00-001 Warszawa, Polska".
     *
     * @param  array<string,mixed>  $p
     */
    private function formatLabel(array $p): string
    {
        $parts = [];

        $line1 = '';
        if (! empty($p['name'])) {
            $line1 = (string) $p['name'];
        }
        if (! empty($p['street'])) {
            $street = (string) $p['street'];
            if (! empty($p['housenumber'])) {
                $street .= ' '.$p['housenumber'];
            }
            if ($line1 === '' || $line1 === $p['street']) {
                $line1 = $street;
            } else {
                $line1 .= ', '.$street;
            }
        }
        if ($line1 !== '') {
            $parts[] = $line1;
        }

        $cityLine = trim(
            (string) ($p['postcode'] ?? '').' '.(string) ($p['city'] ?? $p['town'] ?? $p['village'] ?? '')
        );
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        if (! empty($p['country'])) {
            $parts[] = (string) $p['country'];
        }

        return implode(', ', array_filter($parts));
    }
}
