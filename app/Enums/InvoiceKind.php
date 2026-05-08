<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceKind: string
{
    case Fv = 'fv';                  // Faktura VAT (regular)
    case FvProforma = 'fv_proforma'; // Faktura proforma (offer, not accounting)
    case FvKorekta = 'fv_korekta';   // Faktura korygująca (correction)

    public function label(): string
    {
        return __('enums.invoice_kind.'.$this->value);
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Fv => 'FV',
            self::FvProforma => 'PRO',
            self::FvKorekta => 'KOR',
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
