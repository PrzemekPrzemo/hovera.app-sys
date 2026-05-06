<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrencePattern: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Codziennie',
            self::Weekly => 'Co tydzień',
            self::Monthly => 'Co miesiąc',
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
