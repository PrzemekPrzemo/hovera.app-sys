<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Central\FuelPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransportScrapeFuelCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_writes_snapshot_to_central_fuel_prices(): void
    {
        Http::fake([
            '*' => Http::response('<table><tr><td>Olej napędowy (ON)</td><td>6,99 zł/L</td></tr></table>'),
        ]);

        $this->artisan('transport:scrape-fuel', ['--fuel-type' => 'diesel'])
            ->expectsOutputToContain('6.99 PLN/L')
            ->expectsOutputToContain('Snapshot zapisany')
            ->assertSuccessful();

        $row = FuelPrice::query()->where('fuel_type', 'diesel')->first();
        $this->assertNotNull($row);
        $this->assertSame('6.99', (string) $row->price_pln);
        $this->assertSame('epetrol', $row->source);
    }

    public function test_command_is_idempotent_on_same_day(): void
    {
        Http::fake([
            '*' => Http::response('<div>ON 6,99</div>'),
        ]);

        $this->artisan('transport:scrape-fuel')->assertSuccessful();
        $this->artisan('transport:scrape-fuel')->assertSuccessful();

        $this->assertSame(1, FuelPrice::query()->count());
    }

    public function test_dry_run_does_not_persist(): void
    {
        Http::fake([
            '*' => Http::response('<div>ON 6,99</div>'),
        ]);

        $this->artisan('transport:scrape-fuel', ['--dry-run' => true])
            ->expectsOutputToContain('--dry-run')
            ->assertSuccessful();

        $this->assertSame(0, FuelPrice::query()->count());
    }

    public function test_command_fails_gracefully_on_scrape_error(): void
    {
        Http::fake(['*' => Http::response('<div>nothing here</div>')]);

        $this->artisan('transport:scrape-fuel')->assertFailed();
        $this->assertSame(0, FuelPrice::query()->count());
    }
}
