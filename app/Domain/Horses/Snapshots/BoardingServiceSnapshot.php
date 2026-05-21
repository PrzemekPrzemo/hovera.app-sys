<?php

declare(strict_types=1);

namespace App\Domain\Horses\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Snapshot aktywnej boarding service dla konia. Łączy `boarding_services`
 * z pivot'em `horse_boarding_services` (price_override, quantity, daty).
 *
 * `effectivePriceCents` = price_override jeśli ustawione, inaczej cena
 * z BoardingService. `frequency` reuse'uje stable enum jako string.
 */
final readonly class BoardingServiceSnapshot
{
    public function __construct(
        public string $serviceId,
        public string $name,
        public ?string $description,
        public string $unit,
        public string $frequency,        // 'daily' | 'weekly' | 'monthly' | 'per_use' | 'once'
        public int $effectivePriceCents,
        public float $quantity,
        public string $currency,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
        public ?string $notes,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'frequency' => $this->frequency,
            'effective_price_cents' => $this->effectivePriceCents,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'starts_at' => $this->startsAt?->toDateString(),
            'ends_at' => $this->endsAt?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
