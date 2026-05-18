<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\ServiceAreas\TransportServiceAreaManager;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TransportServiceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TransportServiceAreaManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_voivodeships_returns_16(): void
    {
        $this->assertCount(16, TransportServiceAreaManager::allVoivodeships());
        $this->assertContains('mazowieckie', TransportServiceAreaManager::allVoivodeships());
        $this->assertContains('zachodniopomorskie', TransportServiceAreaManager::allVoivodeships());
    }

    public function test_list_for_empty_tenant_is_empty(): void
    {
        $tenant = $this->makeTransporter();
        $this->assertSame([], app(TransportServiceAreaManager::class)->listFor($tenant));
    }

    public function test_sync_inserts_new_voivodeships(): void
    {
        $tenant = $this->makeTransporter();

        app(TransportServiceAreaManager::class)->sync($tenant, ['mazowieckie', 'łódzkie']);

        $stored = TransportServiceArea::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->pluck('voivodeship')
            ->all();
        sort($stored);
        $this->assertSame(['mazowieckie', 'łódzkie'], $stored);
    }

    public function test_sync_removes_unselected(): void
    {
        $tenant = $this->makeTransporter();
        $mgr = app(TransportServiceAreaManager::class);

        $mgr->sync($tenant, ['mazowieckie', 'śląskie', 'lubelskie']);
        $mgr->sync($tenant, ['mazowieckie']);   // remove śląskie + lubelskie

        $stored = $mgr->listFor($tenant);
        $this->assertSame(['mazowieckie'], $stored);
    }

    public function test_sync_ignores_invalid_voivodeship_names(): void
    {
        $tenant = $this->makeTransporter();

        app(TransportServiceAreaManager::class)->sync($tenant, ['mazowieckie', 'WLKP-FAKE', 'INVALID']);

        $stored = app(TransportServiceAreaManager::class)->listFor($tenant);
        $this->assertSame(['mazowieckie'], $stored);
    }

    public function test_effective_coverage_includes_adjacent_voivodeships(): void
    {
        config()->set('transport.voivodeship_adjacency', [
            'mazowieckie' => ['łódzkie', 'podlaskie'],
            'łódzkie' => ['mazowieckie', 'śląskie'],
        ]);

        $tenant = $this->makeTransporter();
        app(TransportServiceAreaManager::class)->sync($tenant, ['mazowieckie']);

        $coverage = app(TransportServiceAreaManager::class)->effectiveCoverage($tenant);
        sort($coverage);

        $this->assertSame(['mazowieckie', 'podlaskie', 'łódzkie'], $coverage);
    }

    public function test_sync_is_idempotent_on_repeat_call(): void
    {
        $tenant = $this->makeTransporter();
        $mgr = app(TransportServiceAreaManager::class);

        $mgr->sync($tenant, ['mazowieckie', 'łódzkie']);
        $mgr->sync($tenant, ['mazowieckie', 'łódzkie']);

        $this->assertSame(2, TransportServiceArea::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->count());
    }

    public function test_filament_page_route_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('filament.transport.pages.service-areas'));
    }

    private function makeTransporter(): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
