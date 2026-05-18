<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Data;

use App\Domain\Transport\Routing\Data\Coords;

final readonly class GeocodedAddress
{
    public function __construct(
        public string $displayName,        // pełna nazwa wyświetlana ("ul. Marymoncka 1, Warszawa")
        public Coords $coords,
        public ?string $countryCode = null,
        // Dla adresów w PL: wojewodztwo (np. "mazowieckie") — używamy do
        // dispatch'u leadów w trybie broadcast (matching service_areas).
        public ?string $voivodeship = null,
    ) {}
}
