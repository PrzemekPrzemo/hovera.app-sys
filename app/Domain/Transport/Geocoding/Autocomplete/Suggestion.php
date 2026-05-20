<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Autocomplete;

/**
 * Pojedyncza podpowiedź adresu — wynik PlacesAutocompleteProvider::suggest().
 *
 * `label` — pełna sformatowana nazwa (display name w UI).
 * `lat/lng` — koordynaty (opcjonalne, część providerów nie zwraca koord
 *             w autocomplete tylko w follow-up "retrieve" call).
 * `providerId` — żeby wiedzieć z kogo strzał (debugging/metric).
 */
final readonly class Suggestion
{
    public function __construct(
        public string $label,
        public ?float $lat = null,
        public ?float $lng = null,
        public string $providerId = '',
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'provider' => $this->providerId,
        ];
    }
}
