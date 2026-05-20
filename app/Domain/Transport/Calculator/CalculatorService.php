<?php

declare(strict_types=1);

namespace App\Domain\Transport\Calculator;

use App\Domain\Transport\Calculator\Data\CalculationOptions;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Currency\NbpExchangeRateService;
use App\Domain\Transport\Fuel\FuelPriceService;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\RoutingService;
use App\Enums\CalculationMode;
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
        private readonly NbpExchangeRateService $exchange,
    ) {}

    public function calculate(
        Tenant $tenant,
        Coords $from,
        Coords $to,
        ?CalculationOptions $options = null,
    ): Quotation {
        $options ??= new CalculationOptions;
        $settings = TransportSettings::current();

        // Konwersje pojazdowych parametrów dla ORS HGV profile_params:
        //   - kg → tons (ORS wymaga ton, np. 7.5)
        //   - cm → meters (ORS wymaga metrów, np. 3.8)
        // null gdy user nie podał — routing fallbackuje na default HGV
        // bez restrykcji.
        $weightTons = $options->vehicleGrossWeightKg !== null
            ? round($options->vehicleGrossWeightKg / 1000, 2)
            : null;
        $heightMeters = $options->vehicleHeightCm !== null
            ? round($options->vehicleHeightCm / 100, 2)
            : null;

        $routeOptions = new RouteOptions(
            profile: $options->routingProfile,
            avoidTolls: $options->avoidTolls,
            avoidFerries: $options->avoidFerries,
            weightTons: $weightTons,
            heightMeters: $heightMeters,
        );

        $route = $this->routing->calculate($tenant, $from, $to, $routeOptions);

        // Tryb kalkulacji (user feedback: "jazda w jedną stronę / w dwie strony /
        // bezpośredni powrót do domu").
        //
        //   - OneWay     : tylko A→B
        //   - RoundTrip  : A→B + B→A — zakładamy tę samą trasę (×2)
        //   - ReturnHome : A→B + B→base. Wymaga home_lat/home_lng — soft
        //                  fallback do RoundTrip gdy nie ustawione.
        $mode = $options->resolveMode();
        $distanceKm = $route->distanceKm;

        if ($mode === CalculationMode::RoundTrip) {
            $distanceKm *= 2;
        } elseif ($mode === CalculationMode::ReturnHome) {
            $homeLat = $settings->home_lat !== null ? (float) $settings->home_lat : null;
            $homeLng = $settings->home_lng !== null ? (float) $settings->home_lng : null;

            if ($homeLat !== null && $homeLng !== null) {
                $returnLeg = $this->routing->calculate(
                    $tenant,
                    $to,
                    new Coords($homeLat, $homeLng),
                    $routeOptions,
                );
                $distanceKm += $returnLeg->distanceKm;
            } else {
                // Brak bazy w settings — fallback na RoundTrip żeby calculate()
                // nie crashował. Calculator UI ostrzega w widoku.
                $distanceKm *= 2;
            }
        }

        $rateUsed = $this->resolveRate($settings, $options->loaded);
        $baseCost = round($rateUsed * $distanceKm, 2);

        // Fuel cost: tryb 'surcharge' (default) dolicza tylko różnicę nad
        // cenę bazową; 'full_cost' dolicza pełen koszt paliwa. Patrz
        // docs/MARKETPLACE-ROADMAP.md "Fuel mode toggle".
        $fuelSurcharge = 0.0;
        if ($settings->fuel_surcharge_enabled) {
            $fuelMode = (string) ($settings->fuel_calculation_mode ?? 'surcharge');
            $fuelSurcharge = $fuelMode === 'full_cost'
                ? $this->fuel->calculateFullCost(
                    consumptionLPer100km: (float) $settings->fuel_consumption_l_per_100km,
                    distanceKm: $distanceKm,
                )
                : $this->fuel->calculateSurcharge(
                    consumptionLPer100km: (float) $settings->fuel_consumption_l_per_100km,
                    distanceKm: $distanceKm,
                    basePricePln: (float) $settings->fuel_base_price_pln,
                );
        }

        // Doliczenie za dodatkowe konie: tylko konie POWYŻEJ pierwszego — ten
        // jest w cenie bazowej. Jeśli tenant nie ustawił `extra_horse_fee_default`
        // (default=0), liczenie jest no-opem niezależnie od horsesCount.
        $horsesCount = max(1, $options->horsesCount);
        $extraHorseFeePerHead = (float) $settings->extra_horse_fee_default;
        $extraHorseFeeTotal = round($extraHorseFeePerHead * max(0, $horsesCount - 1), 2);

        // Stałe opłaty (autostrady, prom etc.) — z opcji (override) lub
        // z settings.fixed_fees_default. Każda pozycja {name, amount} —
        // pomijamy non-numeric / ujemne wartości (defensive parse).
        $fixedFees = $this->normaliseFixedFees(
            $options->fixedFees ?? $settings->fixed_fees_default ?? [],
        );
        $fixedFeesTotal = array_sum(array_column($fixedFees, 'amount'));
        $fixedFeesTotal = round($fixedFeesTotal, 2);

        // Subtotal kosztów PRZED minimum_adjustment + surcharge.
        $costsSubtotal = round($baseCost + $fuelSurcharge + $extraHorseFeeTotal + $fixedFeesTotal, 2);
        $minimumCharge = (float) $settings->minimum_charge;
        $minimumAdjustment = max(0.0, round($minimumCharge - $costsSubtotal, 2));

        // Marża procentowa — liczona PO minimum_adjustment, żeby zysk skalował
        // się proporcjonalnie do faktycznej kwoty quote'a (a nie tylko kosztów
        // bazowych poniżej minimum). Specifically passed 0 = brak marży nawet
        // gdy settings ustawione (user opt-out).
        $surchargePercent = $options->surchargePercent
            ?? ($settings->surcharge_percent_default !== null
                ? (float) $settings->surcharge_percent_default
                : 0.0);
        $surchargePercent = max(0.0, $surchargePercent);
        $costsWithMinimum = round($costsSubtotal + $minimumAdjustment, 2);
        $surchargeAmount = round($costsWithMinimum * ($surchargePercent / 100), 2);

        $netTotal = round($costsWithMinimum + $surchargeAmount, 2);
        $vatRate = (float) $settings->vat_rate;
        $vatAmount = round($netTotal * ($vatRate / 100), 2);
        $grossTotal = round($netTotal + $vatAmount, 2);

        // Multi-currency: wszystkie powyższe wartości zostały policzone w
        // PLN (rate_per_km, fuel_base_price_pln, extra_horse_fee są w PLN
        // domain). Gdy target currency != PLN, dzielimy przez kurs średni
        // NBP (PLN → EUR/CZK). Snapshot kursu trafia do Quotation żeby
        // CreateQuote zapisał go w `exchange_rate_to_pln` / `exchange_rate_date`.
        //
        // Patrz docs/MARKETPLACE-ROADMAP.md "Multi-currency z NBP".
        $targetCurrency = strtoupper((string) $settings->currency);
        $exchangeRate = 1.0;
        $exchangeRateDate = null;

        if ($targetCurrency !== 'PLN' && $targetCurrency !== '') {
            $snapshot = $this->exchange->currentRateWithDate($targetCurrency);
            $exchangeRate = $snapshot['rate'];
            $exchangeRateDate = $snapshot['date'];

            if ($exchangeRate > 0 && $exchangeRate !== 1.0) {
                $baseCost = round($baseCost / $exchangeRate, 2);
                $fuelSurcharge = round($fuelSurcharge / $exchangeRate, 2);
                $extraHorseFeeTotal = round($extraHorseFeeTotal / $exchangeRate, 2);
                $extraHorseFeePerHead = round($extraHorseFeePerHead / $exchangeRate, 2);
                $fixedFeesTotal = round($fixedFeesTotal / $exchangeRate, 2);
                $fixedFees = array_map(static fn (array $f) => [
                    'name' => $f['name'],
                    'amount' => round($f['amount'] / $exchangeRate, 2),
                ], $fixedFees);
                $minimumAdjustment = round($minimumAdjustment / $exchangeRate, 2);
                $surchargeAmount = round($surchargeAmount / $exchangeRate, 2);
                $netTotal = round($netTotal / $exchangeRate, 2);
                $vatAmount = round($vatAmount / $exchangeRate, 2);
                $grossTotal = round($grossTotal / $exchangeRate, 2);
                $rateUsed = round($rateUsed / $exchangeRate, 4);
            }
        }

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
            currency: $targetCurrency !== '' ? $targetCurrency : 'PLN',
            routingProvider: $route->providerId,
            polyline: $route->polyline,
            extraHorseFeeTotal: $extraHorseFeeTotal,
            extraHorseFeePerHead: $extraHorseFeePerHead,
            horsesCount: $horsesCount,
            fixedFees: $fixedFees,
            fixedFeesTotal: $fixedFeesTotal,
            surchargePercent: $surchargePercent,
            surchargeAmount: $surchargeAmount,
            exchangeRateToPln: $exchangeRate,
            exchangeRateDate: $exchangeRateDate,
        );
    }

    /**
     * Defensive parse — gdy user wpisze garbage w settings JSON albo
     * frontend prześle nie-array. Zwracamy listę poprawnych pozycji.
     *
     * @param  array<mixed>  $raw
     * @return list<array{name: string, amount: float}>
     */
    private function normaliseFixedFees(array $raw): array
    {
        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $amount = isset($item['amount']) ? (float) $item['amount'] : 0.0;
            if ($name === '' || $amount <= 0) {
                continue;
            }
            $out[] = ['name' => $name, 'amount' => round($amount, 2)];
        }

        return $out;
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
