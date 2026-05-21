<?php

declare(strict_types=1);

namespace App\Domain\Horses\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Pojedynczy pomiar masy konia — DTO read-only zwracany przez
 * OwnerHorseCareService. Bezpieczne do trzymania w Filament page
 * property (poza TenantManager::execute scope'em).
 *
 * `deltaKg` to różnica wagi vs poprzedni pomiar — null dla pierwszego
 * pomiaru. Liczymy w service (mając listę uporządkowaną) żeby blade
 * nie musiał robić cross-row logiki.
 */
final readonly class HorseWeightSnapshot
{
    public function __construct(
        public string $id,
        public Carbon $measuredAt,
        public float $weightKg,
        public ?float $girthCm,
        public ?string $notes,
        public ?float $deltaKg,
    ) {}
}
