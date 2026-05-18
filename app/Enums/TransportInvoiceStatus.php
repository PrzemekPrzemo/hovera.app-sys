<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status faktury transportowej.
 *   Draft     → tworzona, brak number
 *   Issued    → wystawiona (number nadany, due_at ustawione)
 *   Paid      → zapłacona (paid_at ustawione)
 *   Overdue   → wystawiona ale po due_at, niezapłacona
 *   Void      → unieważniona przed sale (np. literówka w nabywcy)
 *   Cancelled → anulowana po sale (rzadkie, używamy korekty zamiast)
 */
enum TransportInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.transport_invoice_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Void, self::Cancelled => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Void, self::Cancelled], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
