<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Test bramki weryfikacji konta dla wysyłki ofert.
 * Patrz docs/TRANSPORT.md (feedback prod): bez verified konto nie może
 * wystawiać ofert (faza A3).
 */
class QuoteSendVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_gate_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_send_blocked_when_tenant_pending_verification(): void
    {
        NotificationFacade::fake();
        $this->bindTenantManager($this->makeTenant(VerificationStatus::Pending));

        $quote = $this->makeQuote(QuoteStatus::Draft, ['customer_email' => 'k@example.com']);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status, 'status MUST remain draft');
        $this->assertNull($quote->fresh()->sent_at);
        $this->assertNull($quote->fresh()->accept_token);
        NotificationFacade::assertNothingSent();
    }

    public function test_send_blocked_when_tenant_under_review(): void
    {
        $this->bindTenantManager($this->makeTenant(VerificationStatus::UnderReview));
        $quote = $this->makeQuote(QuoteStatus::Draft);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_send_blocked_when_tenant_rejected(): void
    {
        $this->bindTenantManager($this->makeTenant(VerificationStatus::Rejected));
        $quote = $this->makeQuote(QuoteStatus::Draft);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_send_passes_when_tenant_verified(): void
    {
        $this->bindTenantManager($this->makeTenant(VerificationStatus::Verified));
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'vehicle_id' => (string) Str::ulid(),
            'driver_id' => (string) Str::ulid(),
        ]);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->sent_at);
        $this->assertNotNull($quote->fresh()->accept_token);
    }

    public function test_send_passes_when_no_active_tenant(): void
    {
        // Pure unit: bez aktywnego tenant'a w TenantManager (np. CLI command)
        // gate przepuszcza (no-op). Stable tenant'y też nie mają verification.
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'vehicle_id' => (string) Str::ulid(),
            'driver_id' => (string) Str::ulid(),
        ]);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }

    public function test_send_blocked_when_vehicle_missing(): void
    {
        $this->bindTenantManager($this->makeTenant(VerificationStatus::Verified));
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'vehicle_id' => null,
            'driver_id' => (string) Str::ulid(),
        ]);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status, 'status MUST stay draft when vehicle not assigned');
        $this->assertNull($quote->fresh()->sent_at);
    }

    public function test_send_blocked_when_driver_missing(): void
    {
        $this->bindTenantManager($this->makeTenant(VerificationStatus::Verified));
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'vehicle_id' => (string) Str::ulid(),
            'driver_id' => null,
        ]);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status, 'status MUST stay draft when driver not assigned');
    }

    public function test_send_blocked_when_both_resources_missing(): void
    {
        $this->bindTenantManager($this->makeTenant(VerificationStatus::Verified));
        $quote = $this->makeQuote(QuoteStatus::Draft);

        QuoteResource::sendQuote($quote);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_ensure_tenant_verified_returns_true_for_stable_tenant(): void
    {
        $stable = Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia',
            'type' => TenantType::Stable,
            'verification_status' => null,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        $this->bindTenantManager($stable);

        $this->assertTrue(QuoteResource::ensureTenantVerified());
    }

    private function bindTenantManager(Tenant $tenant): void
    {
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('setCurrent')->andReturnNull();
            $m->shouldReceive('tenantOrFail')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturnTrue();
        });
    }

    private function makeTenant(VerificationStatus $status): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => $status,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeQuote(QuoteStatus $status, array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'customer_name' => 'Jan Kowalski',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 100, 'duration_seconds' => 3600,
            'routing_provider' => 'ors',
            'rate_per_km' => 4.50, 'base_cost' => 450,
            'net_total' => 800, 'vat_rate' => 23, 'vat_amount' => 184, 'gross_total' => 984,
        ], $overrides));
    }

    private function setUpQuotesTable(): void
    {
        Schema::connection('tenant')->create('quotes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 32)->unique();
            $t->string('status', 16)->default('draft');
            $t->string('customer_name');
            $t->string('customer_email')->nullable();
            $t->string('customer_phone', 40)->nullable();
            $t->string('customer_company')->nullable();
            $t->string('customer_tax_id', 32)->nullable();
            $t->text('customer_address')->nullable();
            $t->string('pickup_address');
            $t->decimal('pickup_lat', 10, 7);
            $t->decimal('pickup_lng', 10, 7);
            $t->string('dropoff_address');
            $t->decimal('dropoff_lat', 10, 7);
            $t->decimal('dropoff_lng', 10, 7);
            $t->date('preferred_date');
            $t->time('preferred_time')->nullable();
            $t->boolean('round_trip')->default(false);
            $t->boolean('loaded')->default(true);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 8, 2);
            $t->unsignedInteger('duration_seconds');
            $t->string('routing_provider', 16);
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 6, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2)->default(0);
            $t->decimal('minimum_adjustment', 10, 2)->default(0);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 4, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->date('valid_until')->nullable();
            $t->string('accept_token', 64)->nullable()->unique();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->string('lead_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('pdf_url')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
