<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Co stajnia robi z koniem na co dzień (oprócz wpisów weterynaryjnych
 * w HealthRecord).
 */
enum StableActivityType: string
{
    case Feeding = 'feeding';
    case Grooming = 'grooming';
    case Turnout = 'turnout';      // wypuszczenie na padok
    case Exercise = 'exercise';    // praca / lonża
    case BoxCleaning = 'box_cleaning';
    case TransportEvent = 'transport_event'; // wyjazd na zawody / inny event
    case Other = 'other';

    public function label(): string
    {
        return __('enums.stable_activity_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Feeding => 'heroicon-o-cake',
            self::Grooming => 'heroicon-o-sparkles',
            self::Turnout => 'heroicon-o-sun',
            self::Exercise => 'heroicon-o-bolt',
            self::BoxCleaning => 'heroicon-o-trash',
            self::TransportEvent => 'heroicon-o-truck',
            self::Other => 'heroicon-o-clipboard-document',
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
