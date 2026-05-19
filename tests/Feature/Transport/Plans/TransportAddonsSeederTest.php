<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Plans;

use App\Models\Central\PlanAddon;
use Database\Seeders\TransportAddonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 6 add-onów globalnych — marketing spec sync.
 */
class TransportAddonsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_six_global_addons(): void
    {
        TransportAddonsSeeder::seed();

        $addons = PlanAddon::query()->where('is_global', true)->get();
        $this->assertCount(6, $addons);

        $codes = $addons->pluck('code')->all();
        $this->assertEqualsCanonicalizing(
            ['migrate_excel', 'migrate_system', 'onboarding_live', 'invoice_setup', 'extra_driver', 'extra_vehicle'],
            $codes,
        );
    }

    public function test_one_time_and_recurring_split(): void
    {
        TransportAddonsSeeder::seed();

        $oneTime = PlanAddon::query()->where('addon_type', PlanAddon::TYPE_ONE_TIME)->count();
        $recurring = PlanAddon::query()->where('addon_type', PlanAddon::TYPE_RECURRING_MONTHLY)->count();

        $this->assertSame(4, $oneTime, '4 one-time addons per spec (migrate_excel, migrate_system, onboarding_live, invoice_setup)');
        $this->assertSame(2, $recurring, '2 recurring/mc addons per spec (extra_driver, extra_vehicle)');
    }

    public function test_multi_currency_prices_stored(): void
    {
        TransportAddonsSeeder::seed();

        $excel = PlanAddon::query()->where('code', 'migrate_excel')->first();
        // 499 PLN base, 119 EUR overlay per spec
        $this->assertSame(49900, $excel->priceFor('PLN'));
        $this->assertSame(11900, $excel->priceFor('EUR'));
        $this->assertSame(9900, $excel->priceFor('GBP'));
        $this->assertSame(19900, $excel->priceFor('AUD'));
        $this->assertSame(21900, $excel->priceFor('NZD'));
    }

    public function test_onboarding_live_stores_decimal_correctly(): void
    {
        TransportAddonsSeeder::seed();

        $onb = PlanAddon::query()->where('code', 'onboarding_live')->first();
        // 9,99 PLN literal — stored as 999 cents (not 1000)
        $this->assertSame(999, $onb->priceFor('PLN'));
        // 2,49 EUR
        $this->assertSame(249, $onb->priceFor('EUR'));
    }

    public function test_seeder_is_idempotent(): void
    {
        TransportAddonsSeeder::seed();
        TransportAddonsSeeder::seed();

        $this->assertSame(6, PlanAddon::query()->where('is_global', true)->count());
    }

    public function test_global_addons_have_null_plan_id(): void
    {
        TransportAddonsSeeder::seed();

        $count = PlanAddon::query()
            ->whereNull('plan_id')
            ->where('is_global', true)
            ->count();
        $this->assertSame(6, $count);
    }
}
