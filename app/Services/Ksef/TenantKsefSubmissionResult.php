<?php

declare(strict_types=1);

namespace App\Services\Ksef;

/**
 * Wynik wywołania `TenantKsefSubmissionService::submit()`. Immutable DTO.
 *
 * Mirror `App\Domain\Transport\Ksef\KsefSubmissionResult` ale dla regular
 * Invoice (string status zamiast TransportKsefStatus enum, bo Invoice::ksef_status
 * jest persistowany jako string — patrz migracja 2026_06_21_180000).
 *
 * Brak surowych tokenów / kluczy w errorPayload — to się trzyma w
 * `Invoice::ksef_error_payload` po stronie persistence, tu dostarczamy
 * tylko fragmenty bezpieczne do UI / API.
 */
final class TenantKsefSubmissionResult
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

    public static function submitted(string $referenceNumber): self
    {
        return new self(TenantKsefSubmissionService::STATUS_SUBMITTED, $referenceNumber, null, null);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function rejected(string $message, array $payload): self
    {
        return new self(TenantKsefSubmissionService::STATUS_REJECTED, null, $message, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function error(string $message, array $payload): self
    {
        return new self(TenantKsefSubmissionService::STATUS_ERROR, null, $message, $payload);
    }

    public function isSuccess(): bool
    {
        return $this->status === TenantKsefSubmissionService::STATUS_SUBMITTED;
    }
}
