<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Central\NbpExchangeRate;
use App\Services\Admin\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Snapshot-style test dla health checks — bez live API calls. Sprawdza
 * tylko configured/not_configured + NBP cache age. Live pingi (gus/ceidg/
 * vies) testowane oddzielnie z Http::fake gdy wartoscowe.
 */
class HealthCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_rows_for_all_integrations(): void
    {
        $rows = app(HealthCheckService::class)->snapshot();

        $keys = array_column($rows, 'key');
        $this->assertContains('gus', $keys);
        $this->assertContains('ceidg', $keys);
        $this->assertContains('vies', $keys);
        $this->assertContains('nbp', $keys);
        $this->assertContains('ksef_central', $keys);
        $this->assertContains('smtp', $keys);
        $this->assertContains('db_central', $keys);
    }

    public function test_gus_status_not_configured_without_creds(): void
    {
        // Brak konfiguracji w SystemSetting → not_configured.
        $row = app(HealthCheckService::class)->gusStatus(live: false);

        $this->assertSame('gus', $row['key']);
        $this->assertSame('not_configured', $row['status']);
    }

    public function test_vies_status_ok_without_config_because_public_api(): void
    {
        // VIES nie wymaga credsow — instant status powinien byc 'ok'.
        $row = app(HealthCheckService::class)->viesStatus(live: false);

        $this->assertSame('vies', $row['key']);
        $this->assertSame('ok', $row['status']);
    }

    public function test_nbp_status_unknown_when_no_cache(): void
    {
        $row = app(HealthCheckService::class)->nbpStatus();

        $this->assertSame('nbp', $row['key']);
        $this->assertSame('unknown', $row['status']);
    }

    public function test_nbp_status_ok_when_fresh_cache(): void
    {
        NbpExchangeRate::create([
            'currency_code' => 'EUR',
            'effective_date' => now()->subDay()->toDateString(),
            'rate_to_pln' => 4.30,
            'created_at' => now()->subHours(2),
        ]);

        $row = app(HealthCheckService::class)->nbpStatus();

        $this->assertSame('ok', $row['status']);
        $this->assertStringContainsString('EUR', $row['detail'] ?? '');
    }

    public function test_nbp_status_degraded_when_stale_cache(): void
    {
        // Cache starsza niż 48h → degraded (NBP API moglo padnac).
        NbpExchangeRate::create([
            'currency_code' => 'USD',
            'effective_date' => now()->subDays(10)->toDateString(),
            'rate_to_pln' => 4.00,
            'created_at' => now()->subDays(5),
        ]);

        $row = app(HealthCheckService::class)->nbpStatus();

        $this->assertSame('degraded', $row['status']);
    }

    public function test_database_status_ok(): void
    {
        $row = app(HealthCheckService::class)->databaseStatus();

        $this->assertSame('db_central', $row['key']);
        $this->assertSame('ok', $row['status']);
    }

    public function test_ksef_central_not_configured_by_default(): void
    {
        $row = app(HealthCheckService::class)->ksefCentralStatus();

        $this->assertSame('ksef_central', $row['key']);
        $this->assertSame('not_configured', $row['status']);
    }
}
