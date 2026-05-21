<?php

declare(strict_types=1);

namespace App\Domain\Horses\Snapshots;

/**
 * Pojedynczy wpis planu żywienia — DTO read-only. `meal` to enum value
 * (morning/noon/evening/night) — blade formatuje przez __('owner/horse_care.meal.*').
 * `amountFormatted` przekazujemy gotowe (np. "2,5 kg") z service żeby
 * blade nie musiał decyzji o decimal/locale.
 */
final readonly class HorseFeedingPlanItemSnapshot
{
    public function __construct(
        public string $id,
        public string $meal,
        public string $feedType,
        public string $amountFormatted,
        public ?string $notes,
        public int $sortOrder,
    ) {}
}
