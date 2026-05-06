<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarEntryStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Zgłoszone',
            self::Confirmed => 'Potwierdzone',
            self::Cancelled => 'Anulowane',
            self::Completed => 'Zakończone',
            self::NoShow => 'Nieobecność',
        };
    }

    /**
     * Only states that actually occupy a resource slot. Cancelled and
     * no-show entries don't conflict with new bookings.
     */
    public function blocksResources(): bool
    {
        return match ($this) {
            self::Requested, self::Confirmed, self::Completed => true,
            self::Cancelled, self::NoShow => false,
        };
    }

    /**
     * @return array<string,string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
