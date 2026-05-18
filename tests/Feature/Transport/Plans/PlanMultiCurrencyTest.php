<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Plans;

use App\Models\Central\Plan;
use Database\Seeders\TransportPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan::priceFor() — multi-currency overlay.
 * Marketing spec: 5 walut (PLN base + EUR/GBP/AUD/NZD overlay).
 */
class PlanMultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_currencies_match_marketing_spec(): void
    {
        $this->assertSame(['PLN', 'EUR', 'GBP', 'AUD', 'NZD'], Plan::supportedCurrencies());
    }

    public function test_price_for_base_currency_returns_base_field(): void
    {
        TransportPlansSeeder::seed();

        $start = Plan::query()->where('code', 'transport_start')->first();
        $this->assertSame(25000, $start->priceFor('PLN', 'monthly'));
        $this->assertSame(270000, $start->priceFor('PLN', 'yearly'));
    }

    public function test_price_for_eur_returns_overlay(): void
    {
        TransportPlansSeeder::seed();

        $start = Plan::query()->where('code', 'transport_start')->first();
        // 59 EUR = 5900 cents per marketing spec
        $this->assertSame(5900, $start->priceFor('EUR', 'monthly'));
        // Business 229 EUR = 22900 cents
        $business = Plan::query()->where('code', 'transport_business')->first();
        $this->assertSame(22900, $business->priceFor('EUR', 'monthly'));
    }

    public function test_price_for_all_currencies_on_pro_plan(): void
    {
        TransportPlansSeeder::seed();

        $pro = Plan::query()->where('code', 'transport_pro')->first();
        // Spec: 549 PLN / 129 EUR / 109 GBP / 219 AUD / 239 NZD
        $this->assertSame(54900, $pro->priceFor('PLN'));
        $this->assertSame(12900, $pro->priceFor('EUR'));
        $this->assertSame(10900, $pro->priceFor('GBP'));
        $this->assertSame(21900, $pro->priceFor('AUD'));
        $this->assertSame(23900, $pro->priceFor('NZD'));
    }

    public function test_enterprise_plan_returns_null_for_all_currencies(): void
    {
        TransportPlansSeeder::seed();

        $enterprise = Plan::query()->where('code', 'transport_enterprise')->first();
        foreach (Plan::supportedCurrencies() as $cur) {
            $this->assertNull(
                $enterprise->priceFor($cur),
                "Enterprise must be null for {$cur} (custom pricing)"
            );
        }
    }

    public function test_price_for_unknown_currency_returns_null(): void
    {
        TransportPlansSeeder::seed();

        $start = Plan::query()->where('code', 'transport_start')->first();
        $this->assertNull($start->priceFor('JPY'));
        $this->assertNull($start->priceFor('USD'));
    }

    public function test_case_insensitive_currency_match(): void
    {
        TransportPlansSeeder::seed();

        $start = Plan::query()->where('code', 'transport_start')->first();
        $this->assertSame(25000, $start->priceFor('pln'));
        $this->assertSame(5900, $start->priceFor('eur'));
    }
}
