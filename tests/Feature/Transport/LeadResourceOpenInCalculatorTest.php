<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Filament\Transport\Resources\LeadResource;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pre-fill Calculator z leada (LeadResource::openInCalculator). Wzorzec
 * session-write + redirect — analogiczny do ViewLead::respondToLead. Patrz
 * docs/TRANSPORT.md §16.
 */
class LeadResourceOpenInCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_writes_session_pending_and_redirects_to_calculator(): void
    {
        $tenant = $this->makeTransporter();
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturn(true);
        });
        $this->mock(TenantAuditLogger::class, function ($m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        $lead = $this->makeLead();

        /** @var RedirectResponse $response */
        $response = LeadResource::openInCalculator($lead);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('calculator', $response->getTargetUrl());

        $pending = session('transport.calc.pending');
        $this->assertIsArray($pending);
        $this->assertSame($lead->id, $pending['lead_id']);
        $this->assertSame('Warszawa', $pending['from_address']);
        $this->assertSame('Kraków', $pending['to_address']);
        $this->assertSame(52.2297, $pending['pickup_lat']);
        $this->assertSame(2, $pending['horse_count']);
        $this->assertSame('Anna Nowak', $pending['customer_name']);
        $this->assertSame('anna@test.pl', $pending['customer_email']);
    }

    public function test_lead_id_is_preserved_for_quote_backlink(): void
    {
        $tenant = $this->makeTransporter();
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturn(true);
        });
        $this->mock(TenantAuditLogger::class, function ($m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        $lead = $this->makeLead();
        LeadResource::openInCalculator($lead);

        // Session pending zawiera lead_id który CreateQuote::fillForm konsumuje
        // i zapisuje na Quote'cie jako backlink (CreateQuote::$pendingLeadId).
        $this->assertSame($lead->id, session('transport.calc.pending')['lead_id']);
    }

    public function test_pending_includes_lat_lng_for_geocoding_skip(): void
    {
        $tenant = $this->makeTransporter();
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturn(true);
        });
        $this->mock(TenantAuditLogger::class, function ($m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        $lead = $this->makeLead();
        LeadResource::openInCalculator($lead);

        // Calculator pomija call do Mapbox jeśli session pending ma lat/lng
        // (wzorzec ze starego repo: PREFILL.from_lat → skip geocode).
        $pending = session('transport.calc.pending');
        $this->assertSame(52.2297, $pending['pickup_lat']);
        $this->assertSame(21.0122, $pending['pickup_lng']);
        $this->assertSame(50.0413, $pending['dropoff_lat']);
        $this->assertSame(19.9362, $pending['dropoff_lng']);
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
            'originator_name' => 'Anna Nowak',
            'originator_email' => 'anna@test.pl',
            'originator_phone' => '+48 600 100 200',
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 52.2297,
            'pickup_lng' => 21.0122,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 50.0413,
            'dropoff_lng' => 19.9362,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'preferred_time' => '08:00',
            'horse_count' => 2,
            'notes' => 'Dwa konie ogiery, klatki podzielone.',
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);
    }
}
