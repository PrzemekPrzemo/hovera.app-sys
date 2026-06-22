<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceKind: string
{
    case Fv = 'fv';                  // Faktura VAT (regular)
    case FvProforma = 'fv_proforma'; // Faktura proforma (offer, not accounting)
    case FvKorekta = 'fv_korekta';   // Faktura korygująca (correction)
    case FvUproszczona = 'fv_uproszczona'; // Faktura uproszczona (≤450 PLN brutto, art. 106e ust. 5 pkt 3)
    case FvZaliczkowa = 'fv_zaliczkowa'; // Faktura zaliczkowa (advance payment — multi 1:N do final FV)

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
            self::FvUproszczona => 'UPR',
            self::FvZaliczkowa => 'ZAL',
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
