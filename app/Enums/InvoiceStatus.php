<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Invoice lifecycle:
 *
 *   draft       — w edycji, jeszcze bez numeru i nie wysłane do klienta
 *   issued      — wystawiona, ma numer + datę wystawienia, czeka na płatność
 *   paid        — opłacona w całości
 *   overdue     — niezapłacona po due_at (computed/derivable, ale persist
 *                 dla łatwiejszych queries)
 *   void        — anulowana (przed wystawieniem do klienta — można ją
 *                 ot tak skasować). Po wystawieniu trzeba korektą zerować.
 *   cancelled   — wystornowana korektą (full reversal). Stays in record.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.invoice_status.'.$this->value);
    }

    public function isTerminal(): bool
    {
        return $this === self::Paid
            || $this === self::Void
            || $this === self::Cancelled;
    }

    public function isPosted(): bool
    {
        // "Posted" = ma numer faktury + jest księgowo widoczna.
        return ! in_array($this, [self::Draft, self::Void], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
