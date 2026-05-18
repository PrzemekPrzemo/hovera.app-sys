<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef;

use App\Enums\TransportKsefStatus;

/**
 * Wynik refreshStatus() — pollujemy KSeF po wcześniejszym submit by
 * sprawdzić, czy MF zaakceptowało/odrzuciło fakturę.
 */
final class KsefStatusResult
{
    /**
     * @param  array<string,mixed>|null  $errorPayload
     */
    private function __construct(
        public readonly TransportKsefStatus $status,
        public readonly ?string $referenceNumber,
        public readonly ?string $errorMessage,
        public readonly ?array $errorPayload,
    ) {}

    public static function pending(string $referenceNumber): self
    {
        return new self(TransportKsefStatus::Submitted, $referenceNumber, null, null);
    }

    public static function accepted(string $referenceNumber): self
    {
        return new self(TransportKsefStatus::Accepted, $referenceNumber, null, null);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function rejected(string $message, array $payload, ?string $referenceNumber = null): self
    {
        return new self(TransportKsefStatus::Rejected, $referenceNumber, $message, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function error(string $message, array $payload): self
    {
        return new self(TransportKsefStatus::Error, null, $message, $payload);
    }
}
