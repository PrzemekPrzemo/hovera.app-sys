<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Discriminator dla tenant'a. Jeden tenant = jeden typ — osoba, która prowadzi
 * stajnię i firmę transportową, ma dwa osobne tenanty (multi-tenancy tego nie
 * komplikuje, istniejący tenant-switcher to natywnie obsługuje).
 *
 * Patrz docs/TRANSPORT.md §3.1.
 */
enum TenantType: string
{
    case Stable = 'stable';
    case Transporter = 'transporter';

    public function label(): string
    {
        return __('enums.tenant_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    public function panelId(): string
    {
        return match ($this) {
            self::Stable => 'app',
            self::Transporter => 'transport',
        };
    }
}
