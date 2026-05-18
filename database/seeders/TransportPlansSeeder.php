<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Plany SaaS dla tenant'ów typu transporter — Solo / Pro / Fleet.
 * Patrz docs/TRANSPORT.md §2 D2 + §9 Faza 1.
 *
 * Idempotentny (`updateOrCreate` po `code`). Stripe Price IDs trzeba
 * uzupełnić po stworzeniu Product+Price w Stripe Dashboard:
 *
 *     php artisan tinker
 *     >>> Plan::where('code','transport_solo')->update([
 *     ...     'stripe_price_monthly_id' => 'price_1Abc...',
 *     ...     'stripe_price_yearly_id'  => 'price_1Xyz...',
 *     ... ]);
 */
class TransportPlansSeeder extends Seeder
{
    public static function seed(): void
    {
        $audience = TenantType::Transporter->value;

        $defs = [
            [
                'code' => 'transport_solo',
                'audience' => $audience,
                'name' => 'Transport Solo',
                'currency' => 'PLN',
                'price_monthly_cents' => 14900,
                'price_yearly_cents' => 161000,                  // ~10% off
                'sort_order' => 110,
                'limits' => [
                    'max_vehicles' => 1,
                    'max_drivers' => 2,
                    'max_users' => 1,
                    'max_storage_mb' => 2000,
                    'routing_providers' => ['ors'],              // patrz docs/TRANSPORT.md §7.2
                ],
                'features' => [
                    'bullet_1' => 'Jeden pojazd',
                    'bullet_2' => 'Kalkulator + oferty PDF',
                    'bullet_3' => 'Mapy OpenRouteService (darmowe)',
                    'support' => 'email',
                ],
            ],
            [
                'code' => 'transport_pro',
                'audience' => $audience,
                'name' => 'Transport Pro',
                'currency' => 'PLN',
                'price_monthly_cents' => 34900,
                'price_yearly_cents' => 377000,                  // ~10% off
                'sort_order' => 120,
                'limits' => [
                    'max_vehicles' => 5,
                    'max_drivers' => 10,
                    'max_users' => 5,
                    'max_storage_mb' => 10000,
                    'routing_providers' => ['ors', 'mapbox'],
                ],
                'features' => [
                    'bullet_1' => 'Wszystko z Solo',
                    'bullet_2' => 'Do 5 pojazdów',
                    'bullet_3' => 'Marketplace zleceń',
                    'bullet_4' => 'Własna mapa Mapbox (branded)',
                    'support' => 'email_chat',
                ],
            ],
            [
                'code' => 'transport_fleet',
                'audience' => $audience,
                'name' => 'Transport Fleet',
                'currency' => 'PLN',
                'price_monthly_cents' => 69900,
                'price_yearly_cents' => 754000,                  // ~10% off
                'sort_order' => 130,
                'limits' => [
                    'max_vehicles' => -1,                         // unlimited
                    'max_drivers' => -1,
                    'max_users' => -1,
                    'max_storage_mb' => 50000,
                    'routing_providers' => ['ors', 'mapbox', 'google'],
                ],
                'features' => [
                    'bullet_1' => 'Wszystko z Pro',
                    'bullet_2' => 'Bez limitu pojazdów',
                    'bullet_3' => 'Google Maps Routes (HGV profile)',
                    'bullet_4' => 'Publiczny profil /t/{slug}',
                    'bullet_5' => 'API + webhooks',
                    'support' => 'priority',
                ],
            ],
        ];

        foreach ($defs as $d) {
            Plan::query()->updateOrCreate(['code' => $d['code']], $d);
        }
    }

    public function run(): void
    {
        self::seed();
    }
}
