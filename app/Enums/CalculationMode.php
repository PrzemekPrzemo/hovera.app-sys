<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tryb kalkulacji dystansu dla wyceny transportu.
 *
 *   - OneWay     : A → B (jedna strona, klient płaci tylko za jazdę z koniem)
 *   - RoundTrip  : A → B → A (dwie strony, klient zamawia oba kierunki)
 *   - ReturnHome : A → B → baza_transportera (puste wraca do bazy, klient
 *                  płaci za doliczone km powrotu po pełnym kursie)
 *
 * ReturnHome wymaga `home_address`/`home_lat`/`home_lng` w TransportSettings —
 * inaczej spada do RoundTrip z notyfikacją.
 *
 * Patrz user feedback: "jazda w jedną stronę / jazda w dwie strony /
 * bezpośredni powrót do domu".
 */
enum CalculationMode: string
{
    case OneWay = 'one_way';
    case RoundTrip = 'round_trip';
    case ReturnHome = 'return_home';

    public function label(): string
    {
        return __('enums.calculation_mode.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
