<?php

declare(strict_types=1);

namespace App\Domain\Horses\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Snapshot aktualnego boksu konia. Łączy `box_assignments` (active row,
 * vacated_at = null) z `boxes` (nazwa, monthly_rate_cents) i opcjonalnie
 * `buildings` (nazwa budynku).
 *
 * Field `assignedAt` = kiedy koń wprowadzony do tego boksu; dla
 * historycznych assignment'ów (Faza 2 timeline) doszłoby też vacatedAt.
 */
final readonly class BoxAssignmentSnapshot
{
    public function __construct(
        public string $boxId,
        public string $boxName,
        public ?string $buildingName,
        public ?int $monthlyRateCents,
        public Carbon $assignedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'box_id' => $this->boxId,
            'box_name' => $this->boxName,
            'building_name' => $this->buildingName,
            'monthly_rate_cents' => $this->monthlyRateCents,
            'assigned_at' => $this->assignedAt->toIso8601String(),
        ];
    }
}
