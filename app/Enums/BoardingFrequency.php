<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jak często naliczamy daną usługę pensjonatu. Owner może wybrać:
 *   - daily       (np. siano 5kg/dzień, woda — naliczane × 30 dla miesiąca)
 *   - monthly     (np. ryczałt za pełny pensjonat)
 *   - per_use     (np. transport na zawody, kowal — gdy jest)
 *   - once        (np. opłata wpisowa, depozyt zwrotny)
 */
enum BoardingFrequency: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case PerUse = 'per_use';
    case Once = 'once';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Dziennie',
            self::Monthly => 'Miesięcznie',
            self::PerUse => 'Za użycie',
            self::Once => 'Jednorazowo',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /**
     * Jak wycena per-jednostka przekłada się na miesięczny koszt
     * (heurystyka dla portalu klienta — "tak naliczy się w sumie").
     */
    public function monthlyMultiplier(): int
    {
        return match ($this) {
            self::Daily => 30,    // ~30 dni
            self::Monthly => 1,
            self::PerUse, self::Once => 0,   // nieprzewidywalne
        };
    }
}
