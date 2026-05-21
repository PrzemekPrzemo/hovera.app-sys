<?php

declare(strict_types=1);

namespace App\Domain\Files\Owner\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Snapshot dokumentu konia — paszport, kontrakt boardingu, ubezpieczenie,
 * książeczka szczepień, świadectwa weterynaryjne etc. W odróżnieniu od
 * Photo: ma kind enum + valid_from/valid_until + description.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
final readonly class HorseDocumentSnapshot
{
    public function __construct(
        public string $id,
        public string $stableTenantId,
        public string $name,
        public string $kind,               // HorseDocumentKind enum value
        public ?string $description,
        public string $originalName,
        public string $mime,
        public int $sizeBytes,
        public ?Carbon $validFrom,
        public ?Carbon $validUntil,
        public string $uploadedByRole,
        public ?string $uploaderName,
        public Carbon $createdAt,
    ) {}

    public function isExpired(): bool
    {
        return $this->validUntil !== null && $this->validUntil->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->validUntil === null) {
            return false;
        }
        if ($this->validUntil->isPast()) {
            return false;
        }

        return $this->validUntil->lte(now()->addDays($days));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stable_tenant_id' => $this->stableTenantId,
            'name' => $this->name,
            'kind' => $this->kind,
            'description' => $this->description,
            'original_name' => $this->originalName,
            'mime' => $this->mime,
            'size_bytes' => $this->sizeBytes,
            'valid_from' => $this->validFrom?->toDateString(),
            'valid_until' => $this->validUntil?->toDateString(),
            'uploaded_by_role' => $this->uploadedByRole,
            'uploader_name' => $this->uploaderName,
            'created_at' => $this->createdAt->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
        ];
    }
}
