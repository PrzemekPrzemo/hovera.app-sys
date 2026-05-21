<?php

declare(strict_types=1);

namespace App\Domain\Invoicing\Owner\Snapshots;

/**
 * Linia faktury — embedded w InvoiceDetailSnapshot.
 */
final readonly class InvoiceItemSnapshot
{
    public function __construct(
        public string $id,
        public ?string $horseId,         // central_horse_id (per-koń filter)
        public int $position,
        public string $name,
        public ?string $description,
        public float $quantity,
        public string $unit,
        public string $vatRate,           // "23" | "8" | "zw" | "np" | "oo"
        public int $unitPriceCents,
        public int $netCents,
        public int $vatCents,
        public int $totalCents,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'horse_id' => $this->horseId,
            'position' => $this->position,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'vat_rate' => $this->vatRate,
            'unit_price_cents' => $this->unitPriceCents,
            'net_cents' => $this->netCents,
            'vat_cents' => $this->vatCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
