<?php

declare(strict_types=1);

namespace App\Domain\Transport\Fuel\Fetchers;

use App\Domain\Transport\Fuel\Contracts\FuelPriceFetcher;
use App\Domain\Transport\Fuel\Exceptions\FuelFetchException;
use App\Models\Central\FuelPrice;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Scraper średnich krajowych cen paliw z e-petrol.pl. Strona renderuje
 * tabelę cen z konkretnym markupem, z którego wyciągamy ceny per typ
 * paliwa. To FRAGILE — gdy strona zmieni układ, regex padnie i przejdziemy
 * na fallback (transport.fuel.fallback_price).
 *
 * Endpoint przewidywalny URL — można nadpisać przez `transport.fuel.epetrol.url`
 * w razie zmiany. Testy używają Http::fake z minimalną HTML response.
 */
class EPetrolScraper implements FuelPriceFetcher
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $url = null,
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function fetch(string $fuelType): array
    {
        $url = $this->url ?? (string) config('transport.fuel.epetrol.url', 'https://www.e-petrol.pl/');

        $response = $this->http
            ->timeout($this->timeoutSeconds)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; HoveraFuelBot/1.0)'])
            ->get($url);

        if (! $response->successful()) {
            throw FuelFetchException::networkError('epetrol', "HTTP {$response->status()}");
        }

        $html = (string) $response->body();
        $price = $this->extractPrice($html, $fuelType);

        if ($price === null) {
            throw FuelFetchException::parseError('epetrol', "could not match price for fuel type [{$fuelType}]");
        }

        return [
            'price' => $price,
            'raw' => [
                'url' => $url,
                'fetched_at' => now()->toIso8601String(),
                'http_status' => $response->status(),
            ],
        ];
    }

    /**
     * E-petrol renderuje tabelę z labelami: ON (Olej napędowy) / Pb95 / Pb98 / LPG.
     * Format ceny: `5,89` (przecinek). Szukamy NAJBLIŻSZEJ liczby po pasującym labelu.
     *
     * Markup może się zmienić — w razie awarii regex podać próbkę i poprawić.
     */
    private function extractPrice(string $html, string $fuelType): ?float
    {
        $needle = match ($fuelType) {
            FuelPrice::TYPE_DIESEL => '(?:ON|Olej\s+nap[ęe]dowy)',
            FuelPrice::TYPE_PETROL_95 => 'Pb\s*95',
            FuelPrice::TYPE_PETROL_98 => 'Pb\s*98',
            FuelPrice::TYPE_LPG => 'LPG',
            default => throw FuelFetchException::unsupportedFuelType('epetrol', $fuelType),
        };

        // Szukamy: label + opcjonalnie tagi HTML + cena w formacie X,XX lub X.XX
        $pattern = '/'.$needle.'[^0-9]{1,120}([0-9]{1,2}[,.][0-9]{2})/iu';
        if (! preg_match($pattern, $html, $m)) {
            return null;
        }

        return (float) str_replace(',', '.', $m[1]);
    }
}
