<?php

declare(strict_types=1);

namespace App\Domain\Customers\Exceptions;

use RuntimeException;

class CompanyLookupException extends RuntimeException
{
    public static function notFound(string $source, string $identifier): self
    {
        return new self("No company found in [{$source}] for identifier [{$identifier}].");
    }

    public static function invalidIdentifier(string $source, string $identifier, string $reason): self
    {
        return new self("Identifier [{$identifier}] invalid for [{$source}]: {$reason}.");
    }

    public static function apiError(string $source, int $status, string $body): self
    {
        return new self("Lookup [{$source}] returned HTTP {$status}: {$body}");
    }
}
