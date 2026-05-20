<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use App\Services\TenantAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class QuoteResourceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_qrw_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();

        // Tłumimy audit logger żeby nie próbował zapisywać do nieskonfigurowanej
        // tabeli audit_log w tenant DB.
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_send_quote_transitions_draft_to_sent_and_generates_accept_token(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'vehicle_id' => (string) Str::ulid(),
            'driver_id' => (string) Str::ulid(),
        ]);
        $this->assertNull($quote->accept_token);
        $this->assertNull($quote->sent_at);

        QuoteResource::sendQuote($quote);

        $fresh = $quote->fresh();
        $this->assertSame(QuoteStatus::Sent, $fresh->status);
        $this->assertNotNull($fresh->sent_at);
        $this->assertNotNull($fresh->accept_token);
        $this->assertSame(48, strlen($fresh->accept_token));
    }

    public function test_send_quote_preserves_existing_accept_token_if_present(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'accept_token' => 'preset-token-xyz',
            'vehicle_id' => (string) Str::ulid(),
            'driver_id' => (string) Str::ulid(),
        ]);

        QuoteResource::sendQuote($quote);

        $this->assertSame('preset-token-xyz', $quote->fresh()->accept_token);
    }

    public function test_withdraw_quote_transitions_to_withdrawn(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Sent, [
            'sent_at' => now()->subHour(),
            'accept_token' => 'tkn',
        ]);

        QuoteResource::withdrawQuote($quote);

        $fresh = $quote->fresh();
        $this->assertSame(QuoteStatus::Withdrawn, $fresh->status);
        $this->assertNotNull($fresh->withdrawn_at);
    }

    public function test_resource_routes_are_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();

        $this->assertTrue($names->contains('filament.transport.resources.quotes.index'));
        $this->assertTrue($names->contains('filament.transport.resources.quotes.create'));
        $this->assertTrue($names->contains('filament.transport.resources.quotes.edit'));
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
            $t->unsignedTinyInteger('horses_count')->default(1);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 8, 2);
            $t->unsignedInteger('duration_seconds');
            $t->string('routing_provider', 16);
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 6, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2)->default(0);
            $t->decimal('extra_horse_fee_snapshot', 10, 2)->default(0);
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
