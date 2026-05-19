<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\PlanAddon;
use Illuminate\Database\Seeder;

/**
 * Sponsored placements — 3 paczki wyróżnienia w `/przewoznicy` katalogu.
 * Cennik decision user 2026-05-19. Patrz docs/TRANSPORT.md §16.
 *
 * Master admin generuje `AddonPurchase` z jednego z tych code'ów dla
 * konkretnego transportera → link P24/PayU → webhook → `is_featured=true`
 * + `featured_until=now()+N days`.
 *
 * Idempotentny — `updateOrCreate` po `code` (rerun safe).
 */
class SponsoredPlacementsAddonSeeder extends Seeder
{
    public function run(): void
    {
        self::seed();
    }

    public static function seed(): void
    {
        $addons = [
            [
                'code' => 'sponsored_30d',
                'name' => 'Wyróżnienie w katalogu na 30 dni',
                'description' => 'Profil firmy na górze /przewoznicy + badge "★ Polecany" przez 30 dni.',
                'price_monthly_cents' => 9900, // 99 PLN
                'sort_order' => 100,
            ],
            [
                'code' => 'sponsored_60d',
                'name' => 'Wyróżnienie w katalogu na 60 dni',
                'description' => 'Jak 30 dni, ale dłużej — 60 dni boost'.' (1.8× ceny zamiast 2×).',
                'price_monthly_cents' => 17900, // 179 PLN — bonus vs 2×99
                'sort_order' => 110,
            ],
            [
                'code' => 'sponsored_90d',
                'name' => 'Wyróżnienie w katalogu na 90 dni',
                'description' => 'Najlepsza wartość — 90 dni boost (2.5× cena vs 3×).',
                'price_monthly_cents' => 24900, // 249 PLN — best value vs 3×99
                'sort_order' => 120,
            ],
        ];

        foreach ($addons as $a) {
            PlanAddon::updateOrCreate(
                ['code' => $a['code']],
                array_merge($a, [
                    'addon_type' => PlanAddon::TYPE_ONE_TIME,
                    'is_global' => true,
                    'plan_id' => null,
                    'currency' => 'PLN',
                    'price_yearly_cents' => 0,
                    'is_active' => true,
                ]),
            );
        }
    }
}
