<?php

declare(strict_types=1);

namespace App\Domain\Horses\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Snapshot pełnych danych konia z stable DB — DTO read-only zwracane
 * przez StableHorseSnapshotService. Eloquent model nie nadaje się na
 * "post-execute()" payload bo connection 'tenant' zmienia config po
 * wyjściu z TenantManager::execute(). DTO jest bezpieczne do przekazania
 * w response / Filament page property.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Architektura — DTO zamiast Eloquent".
 */
final readonly class HorseSnapshot
{
    /**
     * @param  list<BoardingServiceSnapshot>  $boardingServices  Aktywne usługi pensjonatu (pivot starts_at..ends_at obejmuje teraźniejszość)
     */
    public function __construct(
        public string $centralHorseId,
        public string $stableHorseId,    // ID w tenant DB stajni (Horse::id)
        public string $stableTenantId,
        public string $name,
        public ?string $breed,
        public ?string $sex,
        public ?string $color,
        public ?Carbon $birthDate,
        public ?string $passportNumber,
        public ?string $microchip,
        public ?string $ueln,
        public ?string $coverImagePath,
        public ?string $notes,
        public ?BoxAssignmentSnapshot $currentBox,
        public array $boardingServices,
        public ?int $estimatedMonthlyCostCents,
    ) {}

    /**
     * Wiek konia w pełnych latach (null jeśli brak birth_date) — helper
     * dla view'u żeby nie liczyć w blade'cie.
     */
    public function ageYears(): ?int
    {
        return $this->birthDate?->diffInYears(now());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'central_horse_id' => $this->centralHorseId,
            'stable_horse_id' => $this->stableHorseId,
            'stable_tenant_id' => $this->stableTenantId,
            'name' => $this->name,
            'breed' => $this->breed,
            'sex' => $this->sex,
            'color' => $this->color,
            'birth_date' => $this->birthDate?->toDateString(),
            'age_years' => $this->ageYears(),
            'passport_number' => $this->passportNumber,
            'microchip' => $this->microchip,
            'ueln' => $this->ueln,
            'cover_image_path' => $this->coverImagePath,
            'notes' => $this->notes,
            'current_box' => $this->currentBox?->toArray(),
            'boarding_services' => array_map(fn (BoardingServiceSnapshot $s) => $s->toArray(), $this->boardingServices),
            'estimated_monthly_cost_cents' => $this->estimatedMonthlyCostCents,
        ];
    }
}
