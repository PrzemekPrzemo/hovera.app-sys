<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Autocomplete\Contracts;

use App\Domain\Transport\Geocoding\Autocomplete\Suggestion;

interface PlacesAutocompleteProvider
{
    public function id(): string;

    /**
     * Zwraca listę podpowiedzi adresów dla zapytania `$query`.
     *
     * @return list<Suggestion>
     */
    public function suggest(string $query, string $countryCode = 'pl', int $limit = 5): array;

    /**
     * Czy provider jest gotowy do użycia (np. ma klucz API, jest enabled
     * w SystemSetting). Pozwala PlacesAutocompleteService zrobić fallback
     * gdy primary nie skonfigurowany.
     */
    public function isAvailable(): bool;
}
