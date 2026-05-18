<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status integracji KSeF dla pojedynczej `TransportInvoice`.
 *
 *   NotSubmitted → jeszcze nie wysłana (default)
 *   Submitted    → wysłana, czekamy na akceptację MF (asynchroniczna)
 *   Accepted     → MF zaakceptował, numer KSeF dostępny
 *   Rejected     → MF odrzucił (błąd merytoryczny — np. zły NIP nabywcy)
 *   Error        → błąd techniczny po naszej / MF stronie (HTTP, timeout)
 *
 * Patrz docs/TRANSPORT.md §14.1 — Hovera robi tylko passthrough,
 * to TRANSPORTER odpowiada za poprawność danych w KSeF.
 */
enum TransportKsefStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Error = 'error';

    public function label(): string
    {
        return __('transport/ksef.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NotSubmitted => 'gray',
            self::Submitted => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Error => 'warning',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Submitted;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected], true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
