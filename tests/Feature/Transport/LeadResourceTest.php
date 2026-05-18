<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Filament\Transport\Resources\LeadResource;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;

class LeadResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_scoped_to_current_transporter_dispatches(): void
    {
        $t1 = $this->makeTransporter();
        $t2 = $this->makeTransporter();

        $lead1 = $this->makeLead();
        $lead2 = $this->makeLead();
        $lead3 = $this->makeLead();

        TransportLeadDispatch::create(['lead_id' => $lead1->id, 'transporter_tenant_id' => $t1->id]);
        TransportLeadDispatch::create(['lead_id' => $lead2->id, 'transporter_tenant_id' => $t1->id]);
        TransportLeadDispatch::create(['lead_id' => $lead3->id, 'transporter_tenant_id' => $t2->id]);

        // Mock TenantManager żeby zwracał t1
        $this->mock(TenantManager::class, function ($m) use ($t1) {
            $m->shouldReceive('current')->andReturn($t1);
        });

        $ids = LeadResource::getEloquentQuery()->pluck('id')->all();
        sort($ids);

        $expected = [$lead1->id, $lead2->id];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    public function test_query_empty_when_no_active_tenant(): void
    {
        $this->makeLead();
        $this->mock(TenantManager::class, function ($m) {
            $m->shouldReceive('current')->andReturn(null);
        });

        $this->assertSame(0, LeadResource::getEloquentQuery()->count());
    }

    public function test_navigation_badge_counts_unseen_dispatches(): void
    {
        $t = $this->makeTransporter();
        $lead = $this->makeLead();
        TransportLeadDispatch::create([
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $t->id,
            'view_status' => 'unseen',
        ]);

        $this->mock(TenantManager::class, function ($m) use ($t) {
            $m->shouldReceive('current')->andReturn($t);
        });

        $this->assertSame('1', LeadResource::getNavigationBadge());
    }

    public function test_badge_returns_null_when_all_seen(): void
    {
        $t = $this->makeTransporter();
        $lead = $this->makeLead();
        TransportLeadDispatch::create([
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $t->id,
            'view_status' => 'seen',
            'seen_at' => now(),
        ]);

        $this->mock(TenantManager::class, function ($m) use ($t) {
            $m->shouldReceive('current')->andReturn($t);
        });

        $this->assertNull(LeadResource::getNavigationBadge());
    }

    public function test_routes_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('filament.transport.resources.leads.index'));
        $this->assertTrue($names->contains('filament.transport.resources.leads.view'));
    }

    private function makeTransporter(): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeLead(): TransportLead
    {
        return TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'Test',
            'pickup_lat' => 0, 'pickup_lng' => 0, 'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Test',
            'dropoff_lat' => 0, 'dropoff_lng' => 0, 'dropoff_voivodeship' => 'mazowieckie',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);
    }
}
