<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedingMeal: string
{
    case Breakfast = 'breakfast';
    case Midday = 'midday';
    case Evening = 'evening';
    case Night = 'night';

    public function label(): string
    {
        return __('enums.feeding_meal.'.$this->value);
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Breakfast => '🌅',
            self::Midday => '☀️',
            self::Evening => '🌇',
            self::Night => '🌙',
        };
    }

    /** Sort order for chronological display in portal view. */
    public function order(): int
    {
        return match ($this) {
            self::Breakfast => 1,
            self::Midday => 2,
            self::Evening => 3,
            self::Night => 4,
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
