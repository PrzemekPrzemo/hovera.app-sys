<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton — jeden wiersz per tenant DB. Pobierany przez ::current(),
 * który auto-tworzy domyślny wiersz przy pierwszym dostępie.
 *
 * Nie używamy HasUlids tutaj — id jest auto-increment, bo jest dokładnie
 * jeden wiersz (singleton wzór). Centralna baza i tak izoluje tenanty
 * fizycznie, więc kolizje numeracji nie istnieją.
 */
class TransportSettings extends Model
{
    protected $connection = 'tenant';

    protected $table = 'transport_settings';

    protected $fillable = [
        'rate_per_km', 'rate_per_km_loaded', 'minimum_charge',
        'fuel_consumption_l_per_100km', 'fuel_surcharge_enabled', 'fuel_base_price_pln',
        'manual_fuel_price_pln',
        'vat_rate', 'currency',
        'routing_provider',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_km' => 'decimal:2',
            'rate_per_km_loaded' => 'decimal:2',
            'minimum_charge' => 'decimal:2',
            'fuel_consumption_l_per_100km' => 'decimal:2',
            'fuel_surcharge_enabled' => 'boolean',
            'fuel_base_price_pln' => 'decimal:2',
            'manual_fuel_price_pln' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'routing_provider' => 'array',
        ];
    }

    /**
     * Domyślne wartości — używane przy auto-tworzeniu pierwszego wiersza.
     * Trzymane tu (nie tylko w migracji), bo Laravel firstOrCreate nie
     * polega na DEFAULT z DB, tylko inserts to co dostanie.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'rate_per_km' => 4.50,
            'rate_per_km_loaded' => null,
            'minimum_charge' => 800.00,
            'fuel_consumption_l_per_100km' => 32.5,
            'fuel_surcharge_enabled' => true,
            'fuel_base_price_pln' => 7.00,
            'vat_rate' => 23.00,
            'currency' => 'PLN',
            'routing_provider' => ['provider' => 'ors'],
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], self::defaults());
    }
}
