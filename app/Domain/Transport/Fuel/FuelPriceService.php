<?php

declare(strict_types=1);

namespace App\Domain\Transport\Fuel;

use App\Models\Central\FuelPrice;
use App\Models\Tenant\TransportSettings;

/**
 * Centralny serwis odczytu aktualnej ceny paliwa dla wyceny transportu.
 * Patrz docs/TRANSPORT.md §4.4 + §3.3.
 *
 * Priorytet źródeł (od najwyższego):
 *   1. transport_settings.manual_fuel_price_pln (per-tenant manual override)
 *   2. najnowszy wpis w central fuel_prices (źródło = scraper e-petrol)
 *      z TTL `transport.fuel.snapshot_max_age_days` (domyślnie 7)
 *   3. fallback z config('transport.fuel.fallback_price', 7.00)
 *
 * Surcharge calc używa current() vs settings.fuel_base_price_pln —
 * dolicza różnicę × spalanie/100 × dystans, gdy fuel_surcharge_enabled.
 */
class FuelPriceService
{
    public function current(string $fuelType = FuelPrice::TYPE_DIESEL): float
    {
        // (1) Per-tenant manual override. TransportSettings::current() działa
        // tylko gdy aktywny jest tenant w TenantManager — w innym kontekście
        // (np. cron command bez tenant'a) pomijamy ten krok.
        try {
            $settings = TransportSettings::current();
            $manual = $settings->manual_fuel_price_pln;
            if ($manual !== null && (float) $manual > 0.0) {
                return (float) $manual;
            }
        } catch (\Throwable) {
            // brak tenant'a — przechodzimy do central source
        }

        // (2) Najnowszy snapshot z central, w obrębie TTL
        $maxAgeDays = (int) config('transport.fuel.snapshot_max_age_days', 7);
        $snapshot = FuelPrice::query()
            ->ofType($fuelType)
            ->where('snapshot_date', '>=', now()->subDays($maxAgeDays)->toDateString())
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first();

        if ($snapshot) {
            return (float) $snapshot->price_pln;
        }

        // (3) Hard fallback
        return (float) config('transport.fuel.fallback_price', 7.00);
    }

    /**
     * Dopłata paliwowa: (cena aktualna − cena bazowa) × spalanie/100 × dystans.
     * Zwraca 0 gdy aktualna ≤ bazowej (taniej niż baza = brak surcharge'a).
     *
     * Spodziewane jednostki:
     *   $consumptionLPer100km — L/100km
     *   $distanceKm           — km
     *   $basePricePln         — PLN/L
     *   wynik                 — PLN
     */
    public function calculateSurcharge(
        float $consumptionLPer100km,
        float $distanceKm,
        float $basePricePln,
        string $fuelType = FuelPrice::TYPE_DIESEL,
    ): float {
        $current = $this->current($fuelType);
        $diff = $current - $basePricePln;
        if ($diff <= 0) {
            return 0.0;
        }

        $litres = ($consumptionLPer100km / 100) * $distanceKm;

        return round($litres * $diff, 2);
    }
}
