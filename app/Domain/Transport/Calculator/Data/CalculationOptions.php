<?php

declare(strict_types=1);

namespace App\Domain\Transport\Calculator\Data;

final readonly class CalculationOptions
{
    public function __construct(
        public bool $loaded = true,                   // czy z koniem (wpływa na rate_per_km_loaded)
        public bool $roundTrip = false,                // czy podwójny dystans (powrót pusty)
        public bool $avoidTolls = false,
        public bool $avoidFerries = false,
        public string $routingProfile = 'truck',
    ) {}
}
