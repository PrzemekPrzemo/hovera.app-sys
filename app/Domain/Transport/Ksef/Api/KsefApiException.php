<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef\Api;

use RuntimeException;

/**
 * Wyjątek z KSeF HTTP API. Niesie tylko bezpieczny payload (status +
 * body + timestamp) — NIGDY tokenu, nigdy headers. KsefHttpClient
 * wystawia ten typ, KsefSessionManager / TransporterKsefService je
 * łapią i przekładają na error_payload faktury.
 */
class KsefApiException extends RuntimeException
{
    /**
     * @param  array<string,mixed>  $payload  Bezpieczny payload (bez secretów)
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly array $payload,
    ) {
        parent::__construct($message);
    }
}
