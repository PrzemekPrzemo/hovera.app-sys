<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Exceptions;

use RuntimeException;
use Throwable;

class RoutingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerId = '',
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function apiError(string $providerId, int $status, string $body): self
    {
        return new self(
            message: "Routing provider [{$providerId}] returned HTTP {$status}: {$body}",
            providerId: $providerId,
            httpStatus: $status,
        );
    }

    public static function noRoute(string $providerId): self
    {
        return new self(
            message: "Routing provider [{$providerId}] returned no route",
            providerId: $providerId,
        );
    }

    public static function missingCredentials(string $providerId): self
    {
        return new self(
            message: "Routing provider [{$providerId}] requires an API key — set it in TransportSettings or env.",
            providerId: $providerId,
        );
    }

    public static function planForbidden(string $providerId, string $planCode): self
    {
        return new self(
            message: "Plan [{$planCode}] does not allow routing provider [{$providerId}].",
            providerId: $providerId,
        );
    }
}
