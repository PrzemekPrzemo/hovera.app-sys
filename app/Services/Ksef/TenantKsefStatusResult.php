<?php

declare(strict_types=1);

namespace App\Services\Ksef;

/**
 * Wynik wywołania `TenantKsefSubmissionService::refreshStatus()`. Mirror
 * `App\Domain\Transport\Ksef\KsefStatusResult` ale dla regular Invoice.
 *
 * Status string-based zgodnie z `Invoice::ksef_status` persistence.
 */
final class TenantKsefStatusResult
{
    /**
     * @param  array<string,mixed>|null  $errorPayload
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $referenceNumber,
        public readonly ?string $errorMessage,
        public readonly ?array $errorPayload,
    ) {}

    public static function pending(string $referenceNumber): self
    {
        return new self(TenantKsefSubmissionService::STATUS_SUBMITTED, $referenceNumber, null, null);
    }

    public static function accepted(string $referenceNumber): self
    {
        return new self(TenantKsefSubmissionService::STATUS_ACCEPTED, $referenceNumber, null, null);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function rejected(string $message, array $payload, ?string $referenceNumber = null): self
    {
        return new self(TenantKsefSubmissionService::STATUS_REJECTED, $referenceNumber, $message, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function error(string $message, array $payload): self
    {
        return new self(TenantKsefSubmissionService::STATUS_ERROR, null, $message, $payload);
    }
}
