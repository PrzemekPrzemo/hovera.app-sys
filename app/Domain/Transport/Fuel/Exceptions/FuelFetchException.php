<?php

declare(strict_types=1);

namespace App\Domain\Transport\Fuel\Exceptions;

use RuntimeException;

class FuelFetchException extends RuntimeException
{
    public static function networkError(string $source, string $reason): self
    {
        return new self("Fuel price fetch failed from [{$source}]: {$reason}");
    }

    public static function parseError(string $source, string $reason): self
    {
        return new self("Could not parse fuel price from [{$source}]: {$reason}");
    }

    public static function unsupportedFuelType(string $source, string $fuelType): self
    {
        return new self("Source [{$source}] does not support fuel type [{$fuelType}]");
    }
}
