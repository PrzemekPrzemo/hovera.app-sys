<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Currency;

use App\Domain\Transport\Currency\NbpExchangeRateService;
use App\Models\Central\NbpExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Multi-currency z NBP exchange rate — patrz docs/MARKETPLACE-ROADMAP.md.
 *
 * Pokrywa:
 *  - PLN → 1.0 (no API call)
 *  - Cache hit z dzisiaj — używamy lokalnie bez fetch'u
 *  - Cache miss → fetch z NBP API, snapshot do DB
 *  - Idempotent fetch — drugi call tego samego dnia nie dubluje wpisu
 *  - Soft-fail przy upadku API: fallback do najnowszego cache'a
 *  - convertPlnTo: dzielenie przez kurs (PLN → target)
 */
class NbpExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pln_always_returns_one_without_api_call(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $rate = app(NbpExchangeRateService::class)->currentRate('PLN');

        $this->assertSame(1.0, $rate);
        Http::assertNothingSent();
    }

    public function test_fetches_and_caches_eur_rate(): void
    {
        $today = now()->toDateString();

        Http::fake([
            'api.nbp.pl/api/exchangerates/rates/A/EUR*' => Http::response([
                'table' => 'A',
                'currency' => 'euro',
                'code' => 'EUR',
                'rates' => [
                    ['no' => '098/A/NBP/2026', 'effectiveDate' => $today, 'mid' => 4.3245],
                ],
            ]),
        ]);

        $rate = app(NbpExchangeRateService::class)->currentRate('EUR');

        $this->assertSame(4.3245, $rate);
        $this->assertDatabaseHas('nbp_exchange_rates', [
            'currency_code' => 'EUR',
            'effective_date' => $today,
            'source' => 'nbp_api',
        ]);
    }

    public function test_uses_cache_when_present_for_today(): void
    {
        $today = now()->toDateString();
        NbpExchangeRate::create([
            'currency_code' => 'EUR',
            'effective_date' => $today,
            'rate_to_pln' => 4.3000,
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $rate = app(NbpExchangeRateService::class)->currentRate('EUR');

        $this->assertSame(4.30, $rate);
        Http::assertNothingSent();
    }

    public function test_falls_back_to_latest_cache_when_api_down(): void
    {
        // Stary cache (np. piątek) — dzisiaj API padło.
        NbpExchangeRate::create([
            'currency_code' => 'CZK',
            'effective_date' => now()->subDays(3)->toDateString(),
            'rate_to_pln' => 0.1750,
        ]);

        Http::fake([
            'api.nbp.pl/*' => Http::response('Service unavailable', 503),
        ]);

        $rate = app(NbpExchangeRateService::class)->currentRate('CZK');

        $this->assertSame(0.1750, $rate);
    }

    public function test_returns_one_when_no_cache_and_api_down(): void
    {
        Http::fake([
            'api.nbp.pl/*' => Http::response('error', 500),
        ]);

        $rate = app(NbpExchangeRateService::class)->currentRate('EUR');

        // Soft-fail: bez kursu zwracamy 1.0 (calculator nie konwertuje,
        // user widzi że quota wyszła w PLN mimo settings).
        $this->assertSame(1.0, $rate);
    }

    public function test_idempotent_cache_no_duplicate_on_second_fetch(): void
    {
        $today = now()->toDateString();
        Http::fake([
            'api.nbp.pl/*' => Http::response([
                'rates' => [['effectiveDate' => $today, 'mid' => 4.3245]],
            ]),
        ]);

        $svc = app(NbpExchangeRateService::class);
        // Drugi call wymusza fetch (bo cache check robi przed insertem
        // tylko gdy data == today; po pierwszym call cache JEST z today).
        $svc->currentRate('EUR');
        // Wymuszamy bypass cache'a (currentRateWithDate fetches again unconditionally).
        $svc->currentRateWithDate('EUR');

        $this->assertSame(1, NbpExchangeRate::query()->where('currency_code', 'EUR')->count());
    }

    public function test_convert_pln_to_eur_divides_by_rate(): void
    {
        $today = now()->toDateString();
        NbpExchangeRate::create([
            'currency_code' => 'EUR',
            'effective_date' => $today,
            'rate_to_pln' => 4.0000,
        ]);

        $eur = app(NbpExchangeRateService::class)->convertPlnTo(1000.0, 'EUR');

        $this->assertSame(250.0, $eur);
    }

    public function test_current_rate_with_date_returns_snapshot_for_quote(): void
    {
        $today = now()->toDateString();
        Http::fake([
            'api.nbp.pl/*' => Http::response([
                'rates' => [['effectiveDate' => $today, 'mid' => 4.3245]],
            ]),
        ]);

        $snapshot = app(NbpExchangeRateService::class)->currentRateWithDate('EUR');

        $this->assertSame(4.3245, $snapshot['rate']);
        $this->assertSame($today, $snapshot['date']);
    }

    public function test_current_rate_with_date_returns_one_for_pln(): void
    {
        $snapshot = app(NbpExchangeRateService::class)->currentRateWithDate('PLN');

        $this->assertSame(1.0, $snapshot['rate']);
        $this->assertNull($snapshot['date']);
    }
}
