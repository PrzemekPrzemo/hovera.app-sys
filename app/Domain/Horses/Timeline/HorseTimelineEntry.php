<?php

declare(strict_types=1);

namespace App\Domain\Horses\Timeline;

use Illuminate\Support\Carbon;

/**
 * Pojedynczy wpis na osi czasu konia. DTO immutable — Eloquent po
 * wyjściu z TenantManager::execute() nie działa, dlatego wszystko
 * snapshotowane.
 *
 * `kind` to top-level kategoria (health/box/weight/activity/photo/document/
 * service), `subkind` to specyficzny typ (np. dla health: vet/dentist/
 * farrier/vaccination — z HealthRecordType enum). Owner UI ikonkę dobiera
 * po `kind`, label z i18n po `kind.subkind`.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 2 — Timeline".
 */
final readonly class HorseTimelineEntry
{
    public const KIND_HEALTH = 'health';

    public const KIND_BOX = 'box';

    public const KIND_WEIGHT = 'weight';

    public const KIND_ACTIVITY = 'activity';

    public const KIND_PHOTO = 'photo';

    public const KIND_DOCUMENT = 'document';

    public const ACTOR_STABLE = 'stable';

    public const ACTOR_OWNER = 'owner';

    public const ACTOR_SYSTEM = 'system';

    public const ALL_KINDS = [
        self::KIND_HEALTH,
        self::KIND_BOX,
        self::KIND_WEIGHT,
        self::KIND_ACTIVITY,
        self::KIND_PHOTO,
        self::KIND_DOCUMENT,
    ];

    /**
     * @param  array<string, mixed>  $payload  Per-kind structured payload (np. dla health: summary, performedBy, cost; dla box: boxName, buildingName)
     */
    public function __construct(
        public string $kind,
        public string $subkind,
        public Carbon $occurredAt,
        public string $sourceId,          // ID źródłowego rekordu (do dedupe / linkowania)
        public string $actorRole,          // stable | owner | system
        public ?string $actorName,
        public string $title,              // Główny label widoczny w UI
        public ?string $description,        // Opcjonalny opis (drugi rząd)
        public ?int $costCents,             // Gdy event miał koszt (vet visit, activity)
        public array $payload = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'subkind' => $this->subkind,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'source_id' => $this->sourceId,
            'actor_role' => $this->actorRole,
            'actor_name' => $this->actorName,
            'title' => $this->title,
            'description' => $this->description,
            'cost_cents' => $this->costCents,
            'payload' => $this->payload,
        ];
    }
}
