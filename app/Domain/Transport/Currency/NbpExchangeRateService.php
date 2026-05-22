<?php

declare(strict_types=1);

namespace App\Domain\Transport\Currency;

use App\Models\Central\NbpExchangeRate;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pobiera średnie kursy NBP (tabela A) i cache'uje per
 * (currency_code, effective_date). Patrz docs/MARKETPLACE-ROADMAP.md
 * "Multi-currency z NBP exchange rate".
 *
 * Endpoint:
 *   GET https://api.nbp.pl/api/exchangerates/rates/A/{code}/?format=json
 *
 * Brak klucza API, public endpoint. NBP publikuje tabelę A codziennie
 * ok. 11:45 w dni robocze; weekendy/święta używają piątkowego kursu.
 *
 * Strategia:
 *   - currentRate(code) — zwraca rate (z DB cache albo świeży fetch).
 *     PLN → 1.0 (no-op). Inne → check cache by today's effective_date,
 *     fallback do najnowszego, fallback do live fetch.
 *   - convertPlnTo(amount, code) — utility do CalculatorService.
 *
 * Soft-fail: gdy NBP API padnie i nie ma cache, zwracamy 1.0 + log
 * warning. Calculator wtedy nie konwertuje — quote wychodzi w PLN
 * niezależnie od ustawienia waluty, user widzi to w UI.
 */
class NbpExchangeRateService
{
    private const BASE_URL = 'https://api.nbp.pl/api/exchangerates/rates/A';

    private const BASE_CURRENCY = 'PLN';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * Aktualny kurs PLN per 1 jednostka `code`. 1.0 dla PLN.
     */
    public function currentRate(string $code): float
    {
        $code = strtoupper(trim($code));
        if ($code === self::BASE_CURRENCY) {
            return 1.0;
        }

        $today = now()->toDateString();

        $cached = NbpExchangeRate::query()
            ->where('currency_code', $code)
            ->orderByDesc('effective_date')
            ->first();

        // Cache hit z dzisiaj — używamy.
        if ($cached !== null && (string) $cached->effective_date === $today) {
            return (float) $cached->rate_to_pln;
        }

        // Próbujemy fetch'u — w przypadku failure'u używamy ostatniego
        // dostępnego cache'a (NBP czasem nie publikuje rate'u w weekend
        // i 24h fallback jest akceptowalny dla wycen).
        $fresh = $this->fetchAndCache($code);
        if ($fresh !== null) {
            return $fresh;
        }

        return $cached !== null ? (float) $cached->rate_to_pln : 1.0;
    }

    /**
     * Snapshot pary (rate, effective_date) dla konkretnej waluty.
     * Używane przez CalculatorService do zapisania na quote'cie.
     *
     * @return array{rate: float, date: ?string}
     */
    public function currentRateWithDate(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === self::BASE_CURRENCY) {
            return ['rate' => 1.0, 'date' => null];
        }

        // Najpierw daj szansę fetch'u (cache też trzymał najnowsze).
        $this->fetchAndCache($code);

        $latest = NbpExchangeRate::query()
            ->where('currency_code', $code)
            ->orderByDesc('effective_date')
            ->first();

        if ($latest === null) {
            return ['rate' => 1.0, 'date' => null];
        }

        return [
            'rate' => (float) $latest->rate_to_pln,
            'date' => (string) $latest->effective_date,
        ];
    }

    /**
     * VAT-correct kurs do wystawienia FV w walucie obcej.
     *
     * Art. 31a ust. 1 ustawy o VAT: przeliczenie kwot w walucie obcej
     * następuje wg średniego kursu NBP z ostatniego dnia ROBOCZEGO
     * poprzedzającego dzień wystawienia faktury (lub powstania
     * obowiązku podatkowego). Dla poniedziałku / dnia po święcie /
     * weekendu cofamy się aż do dnia, w którym NBP publikowało tabelę.
     *
     * Strategia:
     *   1. target = issuanceDate - 1 dzień
     *   2. cache check (currency_code, effective_date == target)
     *   3. fetch z `/rates/A/{code}/{target}/?format=json`; 404 = NBP nie
     *      publikowało w tym dniu → cofamy o kolejny dzień
     *   4. max 10 prób (długi weekend + święto majowe = ~5 dni cofnięcia max)
     *
     * Soft-fail: gdy nie udało się ustalić kursu w żadnym z dni, zwracamy
     * ['rate' => null, 'date' => null, 'source' => null] — caller decyduje
     * czy zablokować wystawienie czy zostawić bez konwersji.
     *
     * @return array{rate: ?float, date: ?string, source: ?string}
     */
    public function rateForInvoiceDate(string $code, Carbon $issuanceDate): array
    {
        $code = strtoupper(trim($code));
        if ($code === self::BASE_CURRENCY) {
            return ['rate' => 1.0, 'date' => $issuanceDate->toDateString(), 'source' => 'pln_base'];
        }

        $target = $issuanceDate->copy()->subDay();
        for ($i = 0; $i < 10; $i++) {
            $dateStr = $target->toDateString();

            $cached = NbpExchangeRate::query()
                ->where('currency_code', $code)
                ->where('effective_date', $dateStr)
                ->first();
            if ($cached !== null) {
                return [
                    'rate' => (float) $cached->rate_to_pln,
                    'date' => $dateStr,
                    'source' => 'nbp_a',
                ];
            }

            $fetched = $this->fetchAndCacheForDate($code, $dateStr);
            if ($fetched !== null) {
                return [
                    'rate' => $fetched['rate'],
                    'date' => $fetched['date'],
                    'source' => 'nbp_a',
                ];
            }

            $target = $target->subDay();
        }

        return ['rate' => null, 'date' => null, 'source' => null];
    }

    /**
     * Konwersja PLN → target currency. Dla PLN no-op.
     */
    public function convertPlnTo(float $amountPln, string $code): float
    {
        $rate = $this->currentRate($code);
        if ($rate <= 0) {
            return $amountPln;
        }

        return round($amountPln / $rate, 2);
    }

    /**
     * Fetch kursu dla KONKRETNEJ daty (NBP zwraca 404 gdy w tym dniu
     * nie było publikacji — weekend/święto). Zwraca null w obu przypadkach
     * (404 i błąd transportu); caller `rateForInvoiceDate` interpretuje
     * null jako "spróbuj poprzedni dzień".
     *
     * @return array{rate: float, date: string}|null
     */
    private function fetchAndCacheForDate(string $code, string $date): ?array
    {
        try {
            $response = $this->http
                ->timeout(10)
                ->acceptJson()
                ->get(self::BASE_URL.'/'.$code.'/'.$date.'/', ['format' => 'json']);

            if (! $response->successful()) {
                // 404 = brak publikacji (weekend/święto). Inne kody loguj.
                if ($response->status() !== 404) {
                    Log::info('NBP API non-2xx for date', [
                        'code' => $code, 'date' => $date, 'status' => $response->status(),
                    ]);
                }

                return null;
            }

            $payload = $response->json();
            $rate = (float) data_get($payload, 'rates.0.mid', 0);
            $effectiveDate = (string) data_get($payload, 'rates.0.effectiveDate', '');
            if ($rate <= 0 || $effectiveDate === '') {
                return null;
            }

            NbpExchangeRate::query()->updateOrCreate(
                ['currency_code' => $code, 'effective_date' => $effectiveDate],
                [
                    'rate_to_pln' => $rate,
                    'source' => 'nbp_api',
                    'raw_payload' => $payload,
                    'created_at' => now(),
                ],
            );

            return ['rate' => $rate, 'date' => $effectiveDate];
        } catch (Throwable $e) {
            Log::warning('NBP API fetch by-date failed', [
                'code' => $code, 'date' => $date, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pobiera świeży kurs z NBP API i cache'uje. Idempotent przez
     * unique constraint (code, effective_date). Zwraca rate gdy
     * fetch się udał, null gdy upadł.
     */
    private function fetchAndCache(string $code): ?float
    {
        try {
            $response = $this->http
                ->timeout(10)
                ->acceptJson()
                ->get(self::BASE_URL.'/'.$code, ['format' => 'json']);

            if (! $response->successful()) {
                Log::info('NBP API non-2xx', ['code' => $code, 'status' => $response->status()]);

                return null;
            }

            $payload = $response->json();
            $rate = (float) data_get($payload, 'rates.0.mid', 0);
            $date = (string) data_get($payload, 'rates.0.effectiveDate', '');
            if ($rate <= 0 || $date === '') {
                return null;
            }

            NbpExchangeRate::query()->updateOrCreate(
                ['currency_code' => $code, 'effective_date' => $date],
                [
                    'rate_to_pln' => $rate,
                    'source' => 'nbp_api',
                    'raw_payload' => $payload,
                    'created_at' => now(),
                ],
            );

            return $rate;
        } catch (Throwable $e) {
            Log::warning('NBP API fetch failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
