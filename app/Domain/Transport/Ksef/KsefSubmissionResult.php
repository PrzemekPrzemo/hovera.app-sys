<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef;

use App\Enums\TransportKsefStatus;

/**
 * Wynik wywołania TransporterKsefService::submit(). Immutable DTO.
 *
 * Brak `errorPayload` z surowym tokenem czy bodym — to się trzyma w
 * `TransportInvoice::ksef_error_payload` po stronie persistence, a tu
 * dostarczamy tylko fragmenty bezpieczne do UI / API.
 */
final class KsefSubmissionResult
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

    public static function submitted(string $referenceNumber): self
    {
        return new self(TransportKsefStatus::Submitted, $referenceNumber, null, null);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function rejected(string $message, array $payload): self
    {
        return new self(TransportKsefStatus::Rejected, null, $message, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public static function error(string $message, array $payload): self
    {
        return new self(TransportKsefStatus::Error, null, $message, $payload);
    }

    public function isSuccess(): bool
    {
        return $this->status === TransportKsefStatus::Submitted;
    }
}
