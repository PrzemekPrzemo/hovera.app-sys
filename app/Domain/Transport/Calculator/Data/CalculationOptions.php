<?php

declare(strict_types=1);

namespace App\Domain\Transport\Calculator\Data;

use App\Enums\CalculationMode;

final readonly class CalculationOptions
{
    public function __construct(
        public bool $loaded = true,                   // czy z koniem (wpływa na rate_per_km_loaded)
        public bool $roundTrip = false,                // legacy: alias dla mode=RoundTrip
        public bool $avoidTolls = false,
        public bool $avoidFerries = false,
        public string $routingProfile = 'truck',
        public ?CalculationMode $mode = null,
    ) {}

    public function resolveMode(): CalculationMode
    {
        if ($this->mode !== null) {
            return $this->mode;
        }

        return $this->roundTrip ? CalculationMode::RoundTrip : CalculationMode::OneWay;
    }
}
