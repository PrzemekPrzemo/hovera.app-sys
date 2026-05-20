<?php

declare(strict_types=1);

namespace App\Domain\Transport\Calculator\Data;

use Livewire\Wireable;

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
 *
 * Implementuje `Wireable` żeby Livewire/Filament mógł trzymać tę DTO w
 * `public ?Quotation $quotation` na Calculator page — bez tego Livewire 3
 * rzuca "Property type not supported in Livewire for property: [{...}]"
 * przy hydracji.
 */
final readonly class Quotation implements Wireable
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
        // Doliczenie za dodatkowe konie powyżej pierwszego: extra_per_horse
        // × (horses_count − 1). Zaokrąglone do 2 miejsc. `extraHorseFeePerHead`
        // to snapshot stawki użytej do liczenia (do PDF breakdown'u).
        public float $extraHorseFeeTotal = 0.0,
        public float $extraHorseFeePerHead = 0.0,
        public int $horsesCount = 1,
        // Stałe opłaty (autostrady, prom etc.) — snapshot list [{name, amount}]
        // i ich suma. `fixedFees` może być pusta = brak doliczeń.
        //
        // @var list<array{name: string, amount: float}>
        public array $fixedFees = [],
        public float $fixedFeesTotal = 0.0,
        // Marża procentowa + jej kwota (suma kosztów × percent/100).
        public float $surchargePercent = 0.0,
        public float $surchargeAmount = 0.0,
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
            'extra_horse_fee_total' => $this->extraHorseFeeTotal,
            'extra_horse_fee_per_head' => $this->extraHorseFeePerHead,
            'horses_count' => $this->horsesCount,
            'fixed_fees' => $this->fixedFees,
            'fixed_fees_total' => $this->fixedFeesTotal,
            'surcharge_percent' => $this->surchargePercent,
            'surcharge_amount' => $this->surchargeAmount,
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

    /** @return array<string,mixed> */
    public function toLivewire(): array
    {
        return [
            'distanceKm' => $this->distanceKm,
            'durationSeconds' => $this->durationSeconds,
            'rateUsed' => $this->rateUsed,
            'baseCost' => $this->baseCost,
            'fuelSurcharge' => $this->fuelSurcharge,
            'extraHorseFeeTotal' => $this->extraHorseFeeTotal,
            'extraHorseFeePerHead' => $this->extraHorseFeePerHead,
            'horsesCount' => $this->horsesCount,
            'fixedFees' => $this->fixedFees,
            'fixedFeesTotal' => $this->fixedFeesTotal,
            'surchargePercent' => $this->surchargePercent,
            'surchargeAmount' => $this->surchargeAmount,
            'minimumAdjustment' => $this->minimumAdjustment,
            'netTotal' => $this->netTotal,
            'vatRate' => $this->vatRate,
            'vatAmount' => $this->vatAmount,
            'grossTotal' => $this->grossTotal,
            'currency' => $this->currency,
            'routingProvider' => $this->routingProvider,
            'polyline' => $this->polyline,
        ];
    }

    /** @param array<string,mixed> $value */
    public static function fromLivewire($value): self
    {
        return new self(
            distanceKm: (float) ($value['distanceKm'] ?? 0),
            durationSeconds: (int) ($value['durationSeconds'] ?? 0),
            rateUsed: (float) ($value['rateUsed'] ?? 0),
            baseCost: (float) ($value['baseCost'] ?? 0),
            fuelSurcharge: (float) ($value['fuelSurcharge'] ?? 0),
            minimumAdjustment: (float) ($value['minimumAdjustment'] ?? 0),
            netTotal: (float) ($value['netTotal'] ?? 0),
            vatRate: (float) ($value['vatRate'] ?? 0),
            vatAmount: (float) ($value['vatAmount'] ?? 0),
            grossTotal: (float) ($value['grossTotal'] ?? 0),
            currency: (string) ($value['currency'] ?? 'PLN'),
            routingProvider: (string) ($value['routingProvider'] ?? ''),
            polyline: isset($value['polyline']) ? (string) $value['polyline'] : null,
            extraHorseFeeTotal: (float) ($value['extraHorseFeeTotal'] ?? 0),
            extraHorseFeePerHead: (float) ($value['extraHorseFeePerHead'] ?? 0),
            horsesCount: (int) ($value['horsesCount'] ?? 1),
            fixedFees: is_array($value['fixedFees'] ?? null) ? $value['fixedFees'] : [],
            fixedFeesTotal: (float) ($value['fixedFeesTotal'] ?? 0),
            surchargePercent: (float) ($value['surchargePercent'] ?? 0),
            surchargeAmount: (float) ($value['surchargeAmount'] ?? 0),
        );
    }
}
