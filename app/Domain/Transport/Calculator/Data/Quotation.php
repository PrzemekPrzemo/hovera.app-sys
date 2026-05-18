<?php

declare(strict_types=1);

namespace App\Domain\Transport\Calculator\Data;

/**
 * Pełna wycena transportu — rozbita na komponenty żeby ofertę PDF można
 * było wyrenderować z pełną transparentnością. Wartości w bazowej walucie
 * (currency), wszystkie wartości pieniężne zaokrąglone do 2 miejsc.
 *
 * baseCost          : rate × distance (z uwzględnieniem loaded/empty)
 * fuelSurcharge     : (current_price − base_price) × consumption/100 × distance
 * minimumAdjustment : różnica do minimum_charge, jeśli base+surcharge < min
 * netTotal          : baseCost + fuelSurcharge + minimumAdjustment
 * vatAmount         : netTotal × (vatRate/100)
 * grossTotal        : netTotal + vatAmount
 */
final readonly class Quotation
{
    public function __construct(
        public float $distanceKm,
        public int $durationSeconds,
        public float $rateUsed,           // PLN/km — zastosowana stawka (loaded vs empty)
        public float $baseCost,
        public float $fuelSurcharge,
        public float $minimumAdjustment,
        public float $netTotal,
        public float $vatRate,
        public float $vatAmount,
        public float $grossTotal,
        public string $currency,
        public string $routingProvider,    // 'ors' / 'mapbox' / 'google'
        public ?string $polyline = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'distance_km' => $this->distanceKm,
            'duration_seconds' => $this->durationSeconds,
            'rate_used' => $this->rateUsed,
            'base_cost' => $this->baseCost,
            'fuel_surcharge' => $this->fuelSurcharge,
            'minimum_adjustment' => $this->minimumAdjustment,
            'net_total' => $this->netTotal,
            'vat_rate' => $this->vatRate,
            'vat_amount' => $this->vatAmount,
            'gross_total' => $this->grossTotal,
            'currency' => $this->currency,
            'routing_provider' => $this->routingProvider,
            'polyline' => $this->polyline,
        ];
    }
}
