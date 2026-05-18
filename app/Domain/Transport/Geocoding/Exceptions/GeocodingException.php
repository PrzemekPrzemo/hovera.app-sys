<?php

declare(strict_types=1);

namespace App\Domain\Transport\Geocoding\Exceptions;

use RuntimeException;

class GeocodingException extends RuntimeException
{
    public static function missingCredentials(string $provider): self
    {
        return new self("Geocoder [{$provider}] requires an API key — set TRANSPORT_MAPBOX_TOKEN in env.");
    }

    public static function notFound(string $query): self
    {
        return new self("Nie znaleziono lokalizacji dla zapytania: \"{$query}\"");
    }

    public static function apiError(string $provider, int $status): self
    {
        return new self("Geocoder [{$provider}] returned HTTP {$status}");
    }
}
