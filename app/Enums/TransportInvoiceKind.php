<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rodzaj faktury transportowej. Patrz docs/TRANSPORT.md §9 faza 3.
 *   Fv          → standard sprzedażowa
 *   Proforma    → zaliczkowa / propozycyjna (nie księgowa)
 *   Korekta     → korekta wcześniej wystawionej FV
 */
enum TransportInvoiceKind: string
{
    case Fv = 'fv';
    case Proforma = 'fv_proforma';
    case Korekta = 'fv_korekta';

    public function label(): string
    {
        return __('enums.transport_invoice_kind.'.$this->value);
    }

    public function defaultTemplate(): string
    {
        return match ($this) {
            self::Fv => 'FT/{YYYY}/{MM}/{seq:4}',
            self::Proforma => 'PRO/{YYYY}/{MM}/{seq:4}',
            self::Korekta => 'KOR/{YYYY}/{MM}/{seq:4}',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
