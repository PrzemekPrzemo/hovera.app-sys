<?php

declare(strict_types=1);

namespace App\Enums;

enum PassStatus: string
{
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktywny',
            self::Exhausted => 'Wykorzystany',
            self::Expired => 'Wygasły',
            self::Cancelled => 'Anulowany',
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
