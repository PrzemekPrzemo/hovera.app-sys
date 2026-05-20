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
        // Liczba koni do przewozu — wpływa na cenę gdy transporter ma
        // ustawione `extra_horse_fee_default` > 0 w TransportSettings.
        // 1 = brak doliczenia. Walidacja UI: 1–30.
        public int $horsesCount = 1,
        // Parametry pojazdu używane przez routing HGV (ORS profile_params.
        // restrictions). Gdy null — routing fallback na default HGV bez
        // restrykcji. Konwersje: kg → tons, cm → metry robi CalculatorService
        // przy przekazywaniu do RouteOptions.
        public ?int $vehicleGrossWeightKg = null,
        public ?int $vehicleHeightCm = null,
    ) {}

    public function resolveMode(): CalculationMode
    {
        if ($this->mode !== null) {
            return $this->mode;
        }

        return $this->roundTrip ? CalculationMode::RoundTrip : CalculationMode::OneWay;
    }
}
