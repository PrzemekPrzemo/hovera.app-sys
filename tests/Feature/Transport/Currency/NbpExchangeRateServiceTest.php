<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Currency;

use App\Domain\Transport\Currency\NbpExchangeRateService;
use App\Models\Central\NbpExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /*
     * rateForInvoiceDate — VAT-correct kurs do wystawienia FV.
     * Art. 31a ust. 1 ustawy o VAT: dzień poprzedzający dzień wystawienia,
     * a w przypadku weekendu/święta — ostatni dzień roboczy w którym
     * NBP publikowało tabelę. Mockujemy NBP API i pokrywamy:
     *   - cache hit dla preceding day → bez fetch'u
     *   - poniedziałek → fetch piątku (sob+ndz = 404)
     *   - PLN → 1.0 bez fetch'u
     *   - long weekend → cofa się dalej
     *   - całkowity failure → null (caller decyduje)
     */

    public function test_rate_for_invoice_uses_cached_preceding_day(): void
    {
        $issuance = Carbon::parse('2026-05-21'); // czwartek
        $preceding = '2026-05-20'; // środa
        NbpExchangeRate::create([
            'currency_code' => 'EUR',
            'effective_date' => $preceding,
            'rate_to_pln' => 4.2900,
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $result = app(NbpExchangeRateService::class)->rateForInvoiceDate('EUR', $issuance);

        $this->assertSame(4.29, $result['rate']);
        $this->assertSame($preceding, $result['date']);
        $this->assertSame('nbp_a', $result['source']);
        Http::assertNothingSent();
    }

    public function test_rate_for_invoice_on_monday_walks_back_to_friday(): void
    {
        $issuance = Carbon::parse('2026-05-25'); // poniedziałek
        // Niedziela 2026-05-24 i sobota 2026-05-23 → 404 z NBP.
        // Piątek 2026-05-22 → publikacja. Wildcard po dacie żeby
        // ?format=json query string nie odpadł.
        Http::fake([
            'api.nbp.pl/api/exchangerates/rates/A/EUR/2026-05-24/*' => Http::response('Not Found', 404),
            'api.nbp.pl/api/exchangerates/rates/A/EUR/2026-05-23/*' => Http::response('Not Found', 404),
            'api.nbp.pl/api/exchangerates/rates/A/EUR/2026-05-22/*' => Http::response([
                'rates' => [['no' => '098/A/NBP/2026', 'effectiveDate' => '2026-05-22', 'mid' => 4.2750]],
            ]),
        ]);

        $result = app(NbpExchangeRateService::class)->rateForInvoiceDate('EUR', $issuance);

        $this->assertSame(4.275, $result['rate']);
        $this->assertSame('2026-05-22', $result['date']);
        $this->assertSame('nbp_a', $result['source']);
        $this->assertDatabaseHas('nbp_exchange_rates', [
            'currency_code' => 'EUR',
            'effective_date' => '2026-05-22',
        ]);
    }

    public function test_rate_for_invoice_pln_is_one_without_api(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $result = app(NbpExchangeRateService::class)
            ->rateForInvoiceDate('PLN', Carbon::parse('2026-05-22'));

        $this->assertSame(1.0, $result['rate']);
        $this->assertSame('pln_base', $result['source']);
        Http::assertNothingSent();
    }

    public function test_rate_for_invoice_returns_null_when_no_data_after_walkback(): void
    {
        // Każda data → 404, brak cache. Po 10 próbach soft-fail.
        Http::fake([
            'api.nbp.pl/*' => Http::response('Not Found', 404),
        ]);

        $result = app(NbpExchangeRateService::class)
            ->rateForInvoiceDate('EUR', Carbon::parse('2026-05-22'));

        $this->assertNull($result['rate']);
        $this->assertNull($result['date']);
        $this->assertNull($result['source']);
    }
}
