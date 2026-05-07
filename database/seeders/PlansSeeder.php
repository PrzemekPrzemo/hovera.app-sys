<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Domyślne 5 tier'ów cennika — Free, Solo, Stable, Pro, Enterprise.
 * Ceny i limity zsynchronizowane z hovera.app/cennik (marketing site).
 *
 * Uruchamiany przez:
 *   php artisan db:seed --class=PlansSeeder
 *   lub przez akcję "Zainstaluj domyślne plany" w /admin/plans
 *
 * Idempotentny — `firstOrCreate` po `code`. Zmiana cen → najpierw
 * edycja w UI lub `forceFill` ręczny.
 */
class PlansSeeder extends Seeder
{
    /**
     * Statyczna metoda dla wywołania z kontekstu UI (action button) bez
     * potrzeby instancjowania Seedera. Instance `run()` poniżej deleguje
     * do tej samej logiki, żeby `php artisan db:seed --class=PlansSeeder`
     * też działało.
     */
    public static function seed(): void
    {
        $defs = [
            [
                'code' => 'free',
                'name' => 'Free',
                'currency' => 'PLN',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'sort_order' => 10,
                'limits' => [
                    'max_horses' => 5,
                    'max_clients' => 10,
                    'max_users' => 1,
                    'max_storage_mb' => 500,
                ],
                'features' => [
                    'bullet_1' => 'Multi-resource calendar',
                    'bullet_2' => 'Podstawowy CRM klientów + koni',
                    'support' => 'community',
                ],
            ],
            [
                'code' => 'solo',
                'name' => 'Solo',
                'currency' => 'PLN',
                'price_monthly_cents' => 9900,
                'price_yearly_cents' => 99000,
                'sort_order' => 20,
                'limits' => [
                    'max_horses' => 10,
                    'max_clients' => 30,
                    'max_users' => 1,
                    'max_storage_mb' => 2000,
                ],
                'features' => [
                    'bullet_1' => 'Wszystko z Free',
                    'bullet_2' => 'Online booking (rezerwacje przez stronę)',
                    'bullet_3' => 'Karnety',
                    'support' => 'email',
                ],
            ],
            [
                'code' => 'stable',
                'name' => 'Stable',
                'currency' => 'PLN',
                'price_monthly_cents' => 24900,
                'price_yearly_cents' => 269000, // ~10% off vs miesięczny
                'sort_order' => 30,
                'limits' => [
                    'max_horses' => 30,
                    'max_clients' => 100,
                    'max_users' => 5,
                    'max_storage_mb' => 10000,
                ],
                'features' => [
                    'bullet_1' => 'Wszystko z Solo',
                    'bullet_2' => 'Karnety + auto-rozliczenia',
                    'bullet_3' => 'Faktury VAT (FV/Proforma/Korekta) + KSeF',
                    'bullet_4' => 'Eksport księgowy',
                    'bullet_5' => 'Dziennik klaczy hodowlanych + źrebiąt',
                    'support' => 'email_chat',
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'currency' => 'PLN',
                'price_monthly_cents' => 49900,
                'price_yearly_cents' => 539000, // ~10% off
                'sort_order' => 40,
                'limits' => [
                    'max_horses' => 100,
                    'max_clients' => -1, // unlimited
                    'max_users' => -1,
                    'max_storage_mb' => 50000,
                ],
                'features' => [
                    'bullet_1' => 'Wszystko z Stable',
                    'bullet_2' => 'Pensjonat + portal właściciela',
                    'bullet_3' => 'Public API + webhooks',
                    'bullet_4' => 'Zaawansowane raporty + dashboardy',
                    'support' => 'priority',
                ],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'currency' => 'PLN',
                'price_monthly_cents' => 0, // custom
                'price_yearly_cents' => 0,
                'sort_order' => 50,
                'limits' => [
                    'max_horses' => -1,
                    'max_clients' => -1,
                    'max_users' => -1,
                    'max_storage_mb' => -1,
                ],
                'features' => [
                    'bullet_1' => 'White-label (logo + domena)',
                    'bullet_2' => 'Multi-location (sieci stadnin)',
                    'bullet_3' => 'SSO (SAML/Google Workspace)',
                    'bullet_4' => 'Dedykowane SLA + opiekun',
                    'bullet_5' => 'Custom rozwoj na zlecenie',
                    'support' => 'dedicated',
                    'is_custom_pricing' => 'true',
                ],
                'is_public' => false, // ukryty w cenniku — kontakt indywidualny
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
