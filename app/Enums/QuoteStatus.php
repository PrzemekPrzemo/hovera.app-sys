<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stan oferty transportowej. Patrz docs/TRANSPORT.md §3.2.
 *
 *   draft     → tworzona, jeszcze nie wysłana do klienta
 *   sent      → wysłana (status snapshot, accept token aktywny)
 *   accepted  → klient zaakceptował (z mail link albo z panelu)
 *   rejected  → klient odrzucił
 *   expired   → minęło valid_until bez akcji klienta
 *   withdrawn → transporter wycofał ofertę
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return __('enums.quote_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Expired => 'warning',
            self::Withdrawn => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Expired, self::Withdrawn], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
