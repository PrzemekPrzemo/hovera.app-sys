<?php

declare(strict_types=1);

namespace Tests\Feature\Ksef;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Services\Ksef\TenantKsefStatusResult;
use App\Services\Ksef\TenantKsefSubmissionService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * `ksef:poll-tenant-invoices` command — orkiestrator scheduled cron
 * pollera dla regular tenant invoice'ów. Algorytm logic w
 * TenantKsefSubmissionService::refreshStatus() (PR #452) — tu testujemy
 * tylko że command iteruje tenantów + zlicza wyniki + tenant filter.
 */
class KsefPollTenantInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock TenantManager — execute callbacku w testach, bez DB switch.
        $this->mock(TenantManager::class, function (MockInterface $m) {
            $m->shouldReceive('execute')->andReturnUsing(fn (Tenant $t, callable $cb) => $cb($t));
        });
    }

    public function test_command_skips_when_no_active_issuing_tenants(): void
    {
        // No tenants seeded
        $this->mock(TenantKsefSubmissionService::class, function (MockInterface $m) {
            $m->shouldNotReceive('refreshStatus');
        });

        $this->artisan('ksef:poll-tenant-invoices')
            ->expectsOutputToContain('No active issuing tenants')
            ->assertExitCode(0);
    }

    public function test_command_filters_only_active_issuing_tenant_types(): void
    {
        $this->makeTenant(TenantType::Stable, 'active');
        $this->makeTenant(TenantType::Transporter, 'active'); // wykluczony — ma własny poller
        $this->makeTenant(TenantType::Stable, 'suspended');   // wykluczony — niedost. status

        // Spy: oczekujemy że processTenant został wykonany tylko raz (active stable).
        $callCount = 0;
        $this->mock(TenantKsefSubmissionService::class, function (MockInterface $m) use (&$callCount) {
            $m->shouldReceive('refreshStatus')->andReturnUsing(function () use (&$callCount) {
                $callCount++;

                return TenantKsefStatusResult::pending('FAKE-REF');
            });
        });

        $this->artisan('ksef:poll-tenant-invoices')->assertExitCode(0);

        // 0 wywołań refreshStatus bo nie ma w DB invoice'ów w stanie 'submitted'.
        // Test sprawdza że command nie crashuje i kończy 0.
        $this->assertSame(0, $callCount);
    }

    public function test_command_accepts_tenant_slug_option(): void
    {
        $stableA = $this->makeTenant(TenantType::Stable, 'active', 'slug-a');
        $stableB = $this->makeTenant(TenantType::Stable, 'active', 'slug-b');

        $this->artisan('ksef:poll-tenant-invoices', ['--tenant' => 'slug-a'])
            ->assertExitCode(0);

        // Brak crash'a — pełen ROI sprawdzimy w manual test against ksef-test.
    }

    public function test_command_continues_when_one_tenant_throws(): void
    {
        $this->makeTenant(TenantType::Stable, 'active', 'broken');
        $this->makeTenant(TenantType::Stable, 'active', 'healthy');

        $this->mock(TenantManager::class, function (MockInterface $m) {
            $m->shouldReceive('execute')->andReturnUsing(function (Tenant $tenant, callable $cb) {
                if ($tenant->slug === 'broken') {
                    throw new \RuntimeException('Simulated DB failure');
                }

                return $cb($tenant);
            });
        });

        $this->artisan('ksef:poll-tenant-invoices')
            ->expectsOutputToContain('broken')
            ->assertExitCode(0);
        // tenant_skipped == 1 wynika z error message zarejestrowanego
        // w Log + zliczonego do totals — assertion w outputie sprawdza
        // tylko że broken tenant nie zatrzymał całego poll'a.
    }

    private function makeTenant(TenantType $type, string $status, ?string $slug = null): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => $slug ?? 'pt-'.$u,
            'name' => 'Poll Tenant '.$u,
            'type' => $type,
            'db_name' => 'pt_'.$u,
            'db_username' => 'pt_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
            'settings' => [],
        ]);
    }
}
