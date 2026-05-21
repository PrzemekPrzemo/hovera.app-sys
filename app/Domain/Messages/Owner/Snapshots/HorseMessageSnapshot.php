<?php

declare(strict_types=1);

namespace App\Domain\Messages\Owner\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Pojedyncza wiadomość w wątku Owner ↔ Stable per koń. Read-only DTO
 * — Eloquent post-execute() jest niedostępny.
 *
 * `stableTenantId` + `id` razem identyfikują wiadomość globalnie (id jest
 * tenant-scoped). URL convention dla mark-read: `/api/owner/messages/
 * {stableTenantId}/{messageId}/read`.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4 — Komunikacja".
 */
final readonly class HorseMessageSnapshot
{
    public function __construct(
        public string $id,
        public string $stableTenantId,
        public string $direction,            // 'from_stable' | 'from_client'
        public ?string $subject,
        public string $body,
        public ?string $senderName,           // Display name nadawcy
        public Carbon $sentAt,
        public ?Carbon $readByClientAt,
        public ?Carbon $readByStableAt,
        public int $attachmentCount,
        public array $attachments,            // [{filename, mime, size, ...}, ...] — JSON snapshot
    ) {}

    public function isUnreadByOwner(): bool
    {
        return $this->direction === 'from_stable' && $this->readByClientAt === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stable_tenant_id' => $this->stableTenantId,
            'direction' => $this->direction,
            'subject' => $this->subject,
            'body' => $this->body,
            'sender_name' => $this->senderName,
            'sent_at' => $this->sentAt->toIso8601String(),
            'read_by_client_at' => $this->readByClientAt?->toIso8601String(),
            'read_by_stable_at' => $this->readByStableAt?->toIso8601String(),
            'attachment_count' => $this->attachmentCount,
            'attachments' => $this->attachments,
            'is_unread_by_owner' => $this->isUnreadByOwner(),
        ];
    }
}
