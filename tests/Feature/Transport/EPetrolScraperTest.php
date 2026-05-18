<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Fuel\Exceptions\FuelFetchException;
use App\Domain\Transport\Fuel\Fetchers\EPetrolScraper;
use App\Models\Central\FuelPrice;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EPetrolScraperTest extends TestCase
{
    public function test_extracts_diesel_price_from_simple_html(): void
    {
        Http::fake([
            '*' => Http::response('<table><tr><td>Olej napędowy (ON)</td><td>6,89 zł/L</td></tr></table>'),
        ]);

        $scraper = new EPetrolScraper(app(HttpFactory::class), url: 'https://example.test/ceny');
        $result = $scraper->fetch(FuelPrice::TYPE_DIESEL);

        $this->assertSame(6.89, $result['price']);
        $this->assertSame('https://example.test/ceny', $result['raw']['url']);
    }

    public function test_handles_dot_decimal_separator(): void
    {
        Http::fake([
            '*' => Http::response('<div>ON 6.45</div>'),
        ]);

        $scraper = new EPetrolScraper(app(HttpFactory::class), url: 'https://example.test/');
        $this->assertSame(6.45, $scraper->fetch(FuelPrice::TYPE_DIESEL)['price']);
    }

    public function test_extracts_petrol_95_price(): void
    {
        Http::fake([
            '*' => Http::response('<span>Pb95</span><span>6,12 zł</span>'),
        ]);

        $scraper = new EPetrolScraper(app(HttpFactory::class), url: 'https://example.test/');
        $this->assertSame(6.12, $scraper->fetch(FuelPrice::TYPE_PETROL_95)['price']);
    }

    public function test_throws_when_label_not_found(): void
    {
        Http::fake([
            '*' => Http::response('<div>strona bez cen paliw</div>'),
        ]);

        $scraper = new EPetrolScraper(app(HttpFactory::class), url: 'https://example.test/');

        $this->expectException(FuelFetchException::class);
        $this->expectExceptionMessage('could not match price');

        $scraper->fetch(FuelPrice::TYPE_DIESEL);
    }

    public function test_throws_on_http_error(): void
    {
        Http::fake([
            '*' => Http::response('', 503),
        ]);

        $scraper = new EPetrolScraper(app(HttpFactory::class), url: 'https://example.test/');

        $this->expectException(FuelFetchException::class);
        $this->expectExceptionMessage('HTTP 503');

        $scraper->fetch(FuelPrice::TYPE_DIESEL);
    }

    public function test_throws_on_unsupported_fuel_type(): void
    {
        Http::fake(['*' => Http::response('<div>...</div>')]);
        $scraper = new EPetrolScraper(app(HttpFactory::class), url: 'https://example.test/');

        $this->expectException(FuelFetchException::class);
        $scraper->fetch('rocket_fuel');
    }
}
