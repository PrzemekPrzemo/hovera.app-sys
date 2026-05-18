<?php

declare(strict_types=1);

namespace App\Domain\Transport\Calculator;

use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Fuel\FuelPriceService;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\RoutingService;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportSettings;

/**
 * Łączy routing + paliwo + stawki tenant'a w pełną wycenę. Patrz
 * docs/TRANSPORT.md §3.3 + §4.4.
 *
 * Wzór wyceny:
 *   base    = rate × distance        (rate_per_km_loaded jeśli loaded=true i ustawione)
 *   surchg  = (curr − base_price) × spalanie/100 × distance   (jeśli fuel_surcharge_enabled)
 *   sub     = base + surchg
 *   min_adj = max(0, minimum_charge − sub)
 *   net     = sub + min_adj
 *   vat     = net × vat_rate/100
 *   gross   = net + vat
 *
 * Wynik (Quotation DTO) jest rozbity per komponent — ofertę PDF można
 * wygenerować z pełną transparentnością ("z czego się składa cena").
 */
class CalculatorService
{
    public function __construct(
        private readonly RoutingService $routing,
        private readonly FuelPriceService $fuel,
    ) {}

    public function calculate(
        Tenant $tenant,
        Coords $from,
        Coords $to,
        ?CalculationOptions $options = null,
    ): Quotation {
        $options ??= new CalculationOptions();
        $settings = TransportSettings::current();

        $route = $this->routing->calculate(
            $tenant,
            $from,
            $to,
            new RouteOptions(
                profile: $options->routingProfile,
                avoidTolls: $options->avoidTolls,
                avoidFerries: $options->avoidFerries,
            ),
        );

        $distanceKm = $route->distanceKm;
        if ($options->roundTrip) {
            $distanceKm *= 2;
        }

        $rateUsed = $this->resolveRate($settings, $options->loaded);
        $baseCost = round($rateUsed * $distanceKm, 2);

        $fuelSurcharge = 0.0;
        if ($settings->fuel_surcharge_enabled) {
            $fuelSurcharge = $this->fuel->calculateSurcharge(
                consumptionLPer100km: (float) $settings->fuel_consumption_l_per_100km,
                distanceKm: $distanceKm,
                basePricePln: (float) $settings->fuel_base_price_pln,
            );
        }

        $subtotal = round($baseCost + $fuelSurcharge, 2);
        $minimumCharge = (float) $settings->minimum_charge;
        $minimumAdjustment = max(0.0, round($minimumCharge - $subtotal, 2));

        $netTotal = round($subtotal + $minimumAdjustment, 2);
        $vatRate = (float) $settings->vat_rate;
        $vatAmount = round($netTotal * ($vatRate / 100), 2);
        $grossTotal = round($netTotal + $vatAmount, 2);

        return new Quotation(
            distanceKm: round($distanceKm, 2),
            durationSeconds: $route->durationSeconds,
            rateUsed: $rateUsed,
            baseCost: $baseCost,
            fuelSurcharge: $fuelSurcharge,
            minimumAdjustment: $minimumAdjustment,
            netTotal: $netTotal,
            vatRate: $vatRate,
            vatAmount: $vatAmount,
            grossTotal: $grossTotal,
            currency: (string) $settings->currency,
            routingProvider: $route->providerId,
            polyline: $route->polyline,
        );
    }

    /**
     * Stawka per km: jeśli `loaded=true` i `rate_per_km_loaded` ustawione →
     * używamy stawki załadunkowej. W innym razie domyślny `rate_per_km`.
     */
    private function resolveRate(TransportSettings $settings, bool $loaded): float
    {
        if ($loaded && $settings->rate_per_km_loaded !== null) {
            $loadedRate = (float) $settings->rate_per_km_loaded;
            if ($loadedRate > 0) {
                return $loadedRate;
            }
        }

        return (float) $settings->rate_per_km;
    }
}
