<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tryb naliczania kosztu paliwa w wycenie transportu.
 *
 *   - Surcharge : (current − base) × consumption/100 × distance.
 *                 Domyślnie: rate_per_km wlicza koszt paliwa po cenie
 *                 bazowej, surcharge kompensuje wzrost.
 *   - FullCost  : current × consumption/100 × distance.
 *                 rate_per_km TYLKO praca + marża, paliwo doliczane 1:1
 *                 wg aktualnej ceny.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md "Calculator: fuel mode toggle".
 */
enum FuelCalculationMode: string
{
    case Surcharge = 'surcharge';

    case FullCost = 'full_cost';

    public function label(): string
    {
        return __('enums.fuel_calculation_mode.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
