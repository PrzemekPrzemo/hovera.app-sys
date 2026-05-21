<?php

declare(strict_types=1);

namespace App\Domain\Files\Owner\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Snapshot zdjęcia konia — używany w cross-tenant list dla owner panel'u.
 * Bezpieczne post-execute (Eloquent connection rozłączony).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
final readonly class HorsePhotoSnapshot
{
    public function __construct(
        public string $id,
        public string $stableTenantId,
        public string $originalName,
        public ?string $caption,
        public string $mime,
        public int $sizeBytes,
        public int $sortOrder,
        public string $uploadedByRole,    // 'stable' | 'client' (owner side bookuje jako 'client')
        public ?string $uploaderName,
        public Carbon $createdAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stable_tenant_id' => $this->stableTenantId,
            'original_name' => $this->originalName,
            'caption' => $this->caption,
            'mime' => $this->mime,
            'size_bytes' => $this->sizeBytes,
            'sort_order' => $this->sortOrder,
            'uploaded_by_role' => $this->uploadedByRole,
            'uploader_name' => $this->uploaderName,
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }
}
