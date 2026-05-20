<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Typ pojazdu w bazie transportera.
 *
 *  - Truck   : pojazd z silnikiem (ciężarówka, van, samochód osobowy). Ma
 *              spalanie i może ciągnąć przyczepę.
 *  - Trailer : przyczepa do koni (bez silnika). Nie ma spalania —
 *              w ofercie kombinowanej (truck + trailer) całe paliwo
 *              liczy się od trucka.
 *
 * Patrz docs/TRANSPORT.md §4.3.
 */
enum VehicleType: string
{
    case Truck = 'truck';
    case Trailer = 'trailer';

    public function label(): string
    {
        return __('enums.vehicle_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
