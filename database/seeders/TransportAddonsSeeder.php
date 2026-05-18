<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\PlanAddon;
use Illuminate\Database\Seeder;

/**
 * Add-ony globalne dla wszystkich planów transportowych. 6 sztuk:
 *  - 4 jednorazowe (migracja danych, setup faktur, onboarding live)
 *  - 2 recurring (dodatkowy kierowca / pojazd ponad limit planu)
 *
 * Wszystkie z `is_global=true` i `plan_id=NULL` — stosują się do każdego
 * planu transport_*. Read-side filtruje:
 *
 *     PlanAddon::where('is_global', true)->where('is_active', true)->get();
 *
 * Cena one-time przechowywana w `price_monthly_cents` (semantyka pola:
 * "kwota podstawowa" — `addon_type` determinuje czy to /mc czy 1×).
 *
 * Onboarding live (9.99 PLN brutto) — celowo niska cena symboliczna,
 * przechowywana jako 999 cents. Marketing copy pokazuje "9,99 zł".
 *
 * Idempotentny — `updateOrCreate` po `code`.
 * Patrz docs/TRANSPORT.md §15.4 + hovera.app/produkt/transport/.
 */
class TransportAddonsSeeder extends Seeder
{
    public static function seed(): void
    {
        $addons = [
            [
                'code' => 'migrate_excel',
                'name' => 'Migracja danych z Excela (do 500 wpisów)',
                'description' => 'Importujemy Twoje klientów, pojazdy i historię transportów z arkusza Excel/CSV.',
                'addon_type' => PlanAddon::TYPE_ONE_TIME,
                'is_global' => true,
                'plan_id' => null,
                'currency' => 'PLN',
                'price_monthly_cents' => 49900,                 // 499 PLN
                'price_yearly_cents' => 0,
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 11900],
                    'GBP' => ['monthly_cents' => 9900],
                    'AUD' => ['monthly_cents' => 19900],
                    'NZD' => ['monthly_cents' => 21900],
                ],
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'migrate_system',
                'name' => 'Migracja danych z innego systemu',
                'description' => 'Eksport ze starego systemu (Mileage, Truckav, własna baza MySQL) + przeniesienie do Hovera.',
                'addon_type' => PlanAddon::TYPE_ONE_TIME,
                'is_global' => true,
                'plan_id' => null,
                'currency' => 'PLN',
                'price_monthly_cents' => 149900,                // 1499 PLN
                'price_yearly_cents' => 0,
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 35900],
                    'GBP' => ['monthly_cents' => 29900],
                    'AUD' => ['monthly_cents' => 59900],
                    'NZD' => ['monthly_cents' => 65900],
                ],
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => 'onboarding_live',
                'name' => 'Onboarding live 1:1 z trenerem',
                'description' => 'Godzinna sesja Google Meet z trenerem — konfiguracja konta, import danych, pytania.',
                'addon_type' => PlanAddon::TYPE_ONE_TIME,
                'is_global' => true,
                'plan_id' => null,
                'currency' => 'PLN',
                // 9.99 PLN — symboliczna cena, marketing copy pokazuje "9,99 zł".
                // 999 cents (nie 1000) żeby zachować literal z Astro.
                'price_monthly_cents' => 999,
                'price_yearly_cents' => 0,
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 249],
                    'GBP' => ['monthly_cents' => 199],
                    'AUD' => ['monthly_cents' => 399],
                    'NZD' => ['monthly_cents' => 449],
                ],
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'invoice_setup',
                'name' => 'Konfiguracja faktur (KSeF + szablon)',
                'description' => 'Podpięcie KSeF, własny szablon PDF, numeracja serii.',
                'addon_type' => PlanAddon::TYPE_ONE_TIME,
                'is_global' => true,
                'plan_id' => null,
                'currency' => 'PLN',
                'price_monthly_cents' => 29900,                 // 299 PLN
                'price_yearly_cents' => 0,
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 6900],
                    'GBP' => ['monthly_cents' => 5900],
                    'AUD' => ['monthly_cents' => 11900],
                    'NZD' => ['monthly_cents' => 12900],
                ],
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'code' => 'extra_driver',
                'name' => 'Dodatkowy kierowca (ponad limit planu)',
                'description' => 'Każdy dodatkowy kierowca powyżej limitu Twojego planu.',
                'addon_type' => PlanAddon::TYPE_RECURRING_MONTHLY,
                'is_global' => true,
                'plan_id' => null,
                'resource_type' => 'drivers',
                'quantity' => 1,
                'currency' => 'PLN',
                'price_monthly_cents' => 2500,                  // 25 PLN/mc
                'price_yearly_cents' => 27000,                  // 270 PLN/rok (~10% off)
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 600, 'yearly_cents' => 6500],
                    'GBP' => ['monthly_cents' => 500, 'yearly_cents' => 5400],
                    'AUD' => ['monthly_cents' => 1000, 'yearly_cents' => 10800],
                    'NZD' => ['monthly_cents' => 1100, 'yearly_cents' => 11900],
                ],
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'code' => 'extra_vehicle',
                'name' => 'Dodatkowy pojazd (ponad limit planu)',
                'description' => 'Każdy dodatkowy pojazd powyżej limitu Twojego planu.',
                'addon_type' => PlanAddon::TYPE_RECURRING_MONTHLY,
                'is_global' => true,
                'plan_id' => null,
                'resource_type' => 'vehicles',
                'quantity' => 1,
                'currency' => 'PLN',
                'price_monthly_cents' => 3500,                  // 35 PLN/mc
                'price_yearly_cents' => 37800,                  // 378 PLN/rok (~10% off)
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 800, 'yearly_cents' => 8600],
                    'GBP' => ['monthly_cents' => 700, 'yearly_cents' => 7500],
                    'AUD' => ['monthly_cents' => 1400, 'yearly_cents' => 15100],
                    'NZD' => ['monthly_cents' => 1500, 'yearly_cents' => 16200],
                ],
                'sort_order' => 60,
                'is_active' => true,
            ],
        ];

        foreach ($addons as $addon) {
            // Globalny add-on: `plan_id=NULL` ⇒ unikalność po samym `code`
            // a nie po (plan_id, code) — robimy manual upsert. Plan-scoped
            // legacy add-ony nie kolidują bo mają inne kody.
            $existing = PlanAddon::query()
                ->whereNull('plan_id')
                ->where('code', $addon['code'])
                ->first();

            if ($existing !== null) {
                $existing->fill($addon)->save();
            } else {
                PlanAddon::query()->create($addon);
            }
        }
    }

    public function run(): void
    {
        self::seed();
    }
}
