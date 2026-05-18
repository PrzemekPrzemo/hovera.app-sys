<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Plany SaaS dla firm transportowych — Start / Pro / Business / Enterprise.
 * Source of truth: hovera.app/produkt/transport/ (komponent `CarrierOnboarding.astro`).
 *
 * Marketing spec (zafixowane 2026-05-18):
 *  - 4 plany; cumulative features (każdy wyższy zawiera wszystko z niższych)
 *  - 5 walut: PLN (base), EUR, GBP, AUD, NZD
 *  - Lock-in 12 miesięcy z gwarancją niezmienności
 *  - Promocja do 2026-07-31
 *  - Trial 1 miesiąc OD WERYFIKACJI dokumentów (nie od signupu)
 *  - Stables używają modułu transport BEZPŁATNIE w ramach swojego planu Hovery
 *
 * Idempotentny (`updateOrCreate` po `code`). Stripe Price IDs trzeba
 * uzupełnić po stworzeniu Product+Price w Stripe Dashboard:
 *
 *     php artisan tinker
 *     >>> Plan::where('code','transport_start')->update([
 *     ...     'stripe_price_monthly_id' => 'price_1Abc...',
 *     ...     'stripe_price_yearly_id'  => 'price_1Xyz...',
 *     ... ]);
 *
 * Mapping marketing code → DB code (Astro `CarrierOnboarding.astro` używa
 * `start|pro|business|enterprise`, my prefix'ujemy `transport_` żeby uniknąć
 * kolizji z planami stajennymi `pro` i `enterprise`).
 *
 *   start       → transport_start
 *   pro         → transport_pro
 *   business    → transport_business
 *   enterprise  → transport_enterprise
 *
 * Patrz docs/TRANSPORT.md §2 D2 + §15.4.
 */
class TransportPlansSeeder extends Seeder
{
    public static function seed(): void
    {
        $audience = TenantType::Transporter->value;

        $defs = [
            [
                'code' => 'transport_start',
                'audience' => $audience,
                'name' => 'Transport Start',
                'currency' => 'PLN',
                'price_monthly_cents' => 25000,         // 250 PLN
                'price_yearly_cents' => 270000,         // 2700 PLN (~10% off vs 12×250)
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 5900, 'yearly_cents' => 63700],
                    'GBP' => ['monthly_cents' => 4900, 'yearly_cents' => 52900],
                    'AUD' => ['monthly_cents' => 9900, 'yearly_cents' => 106900],
                    'NZD' => ['monthly_cents' => 10900, 'yearly_cents' => 117700],
                ],
                'sort_order' => 110,
                'limits' => [
                    'max_drivers' => 4,
                    'max_vehicles' => 4,
                    'max_quotes_per_month' => 100,
                    'max_customers' => 200,
                    'max_storage_mb' => 3000,
                    'routing_providers' => ['ors'],
                ],
                'features' => [
                    'bullets' => [
                        'calculator_hgv',
                        'pdf_quotes_public_acceptance',
                        'crm_clients',
                        'poi_google_import',
                        'calendar_ical',
                        'public_page_pl',
                        'payments_csv_import',
                        'invoices_ksef',
                        'reports_basic',
                        'support_email_24h',
                    ],
                    'highlight' => null,
                    'audience_hint' => 'small_carriers',
                    'support_sla_hours' => 24,
                    'marketing_code' => 'start',
                    'support' => 'email',
                ],
            ],
            [
                'code' => 'transport_pro',
                'audience' => $audience,
                'name' => 'Transport Pro',
                'currency' => 'PLN',
                'price_monthly_cents' => 54900,         // 549 PLN
                'price_yearly_cents' => 593000,         // 5930 PLN (~10% off)
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 12900, 'yearly_cents' => 139300],
                    'GBP' => ['monthly_cents' => 10900, 'yearly_cents' => 117700],
                    'AUD' => ['monthly_cents' => 21900, 'yearly_cents' => 236500],
                    'NZD' => ['monthly_cents' => 23900, 'yearly_cents' => 258100],
                ],
                'sort_order' => 120,
                'limits' => [
                    'max_drivers' => 8,
                    'max_vehicles' => 12,
                    'max_quotes_per_month' => 500,
                    'max_customers' => 1000,
                    'max_storage_mb' => 15000,
                    'routing_providers' => ['ors', 'mapbox'],
                ],
                'features' => [
                    // Cumulative — w Bladzie i tak renderujemy "+ ..." nad listą Start.
                    'bullets' => [
                        'multilang_public_page',
                        'custom_rates_per_client',
                        'auto_toll_estimation',
                        'stop_types_dictionary',
                        'public_gallery',
                    ],
                    'highlight' => 'most_popular',
                    'audience_hint' => 'growing_carriers',
                    'support_sla_hours' => 24,
                    'marketing_code' => 'pro',
                    'support' => 'email_priority',
                ],
            ],
            [
                'code' => 'transport_business',
                'audience' => $audience,
                'name' => 'Transport Business',
                'currency' => 'PLN',
                'price_monthly_cents' => 99900,         // 999 PLN
                'price_yearly_cents' => 1079000,        // 10790 PLN (~10% off)
                'prices_per_currency' => [
                    'EUR' => ['monthly_cents' => 22900, 'yearly_cents' => 247300],
                    'GBP' => ['monthly_cents' => 19900, 'yearly_cents' => 214900],
                    'AUD' => ['monthly_cents' => 39900, 'yearly_cents' => 430900],
                    'NZD' => ['monthly_cents' => 43900, 'yearly_cents' => 474100],
                ],
                'sort_order' => 130,
                'limits' => [
                    'max_drivers' => 15,
                    'max_vehicles' => 25,
                    'max_quotes_per_month' => -1,
                    'max_customers' => -1,
                    'max_storage_mb' => 50000,
                    'routing_providers' => ['ors', 'mapbox', 'google'],
                ],
                'features' => [
                    'bullets' => [
                        'custom_branding',
                        'advanced_reports',
                        'export_csv_json_gdpr',
                        'configurable_toll_rates',
                        'roadmap_priority',
                    ],
                    'highlight' => null,
                    'audience_hint' => 'mid_large_carriers',
                    'support_sla_hours' => 12,
                    'marketing_code' => 'business',
                    'support' => 'priority',
                    'vanity_domain' => true,
                ],
            ],
            [
                'code' => 'transport_enterprise',
                'audience' => $audience,
                'name' => 'Transport Enterprise',
                'currency' => 'PLN',
                // Cena indywidualna — UI ma renderować CTA "Skontaktuj się".
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'prices_per_currency' => null,
                'sort_order' => 140,
                'limits' => [
                    'max_drivers' => -1,
                    'max_vehicles' => -1,
                    'max_quotes_per_month' => -1,
                    'max_customers' => -1,
                    'max_storage_mb' => -1,
                    'routing_providers' => ['ors', 'mapbox', 'google'],
                ],
                'features' => [
                    'bullets' => [
                        'dedicated_environment',
                        'sla_financial_99_9',
                        'live_onboarding',
                        'data_migration_free',
                        'white_label',
                        'api_rest',
                        'dedicated_storage',
                        'custom_integrations',
                    ],
                    'highlight' => null,
                    'audience_hint' => 'enterprise',
                    'is_custom_pricing' => true,
                    'marketing_cta' => 'contact_sales',
                    'marketing_code' => 'enterprise',
                    'support' => 'dedicated',
                    'vanity_domain' => true,
                    'white_label' => true,
                ],
                // Enterprise widoczny w cenniku jako "Skontaktuj się", ale
                // nie wybierany w self-service signupie — tylko ręcznie
                // przez admin'a po zawarciu umowy.
                'is_public' => true,
                'is_active' => true,
            ],
        ];

        foreach ($defs as $d) {
            // Zachowujemy domyślne is_active/is_public dla planów płatnych
            // chyba że seeder explicite ustawia w def (Enterprise).
            $d['is_active'] = $d['is_active'] ?? true;
            $d['is_public'] = $d['is_public'] ?? true;

            Plan::query()->updateOrCreate(['code' => $d['code']], $d);
        }
    }

    public function run(): void
    {
        self::seed();
    }
}
