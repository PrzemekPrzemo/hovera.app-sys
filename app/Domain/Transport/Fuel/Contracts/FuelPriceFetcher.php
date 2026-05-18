<?php

declare(strict_types=1);

namespace App\Domain\Transport\Fuel\Contracts;

interface FuelPriceFetcher
{
    /**
     * Pobiera aktualną krajową średnią cenę paliwa danego typu (PLN/L).
     * Implementacja MOŻE rzucić FuelFetchException w razie problemów
     * z zewnętrznym źródłem — wtedy caller decyduje czy retry / fallback.
     *
     * @return array{price: float, raw: array<string, mixed>}
     */
    public function fetch(string $fuelType): array;
}
