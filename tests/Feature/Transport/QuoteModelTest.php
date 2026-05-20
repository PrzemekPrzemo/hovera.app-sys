<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\QuoteStatus;
use App\Models\Tenant\Quote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteModelTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_quote_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_can_create_quote_with_full_pricing_snapshot(): void
    {
        $quote = Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/0001',
            'status' => QuoteStatus::Draft,
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.2818,
            'pickup_lng' => 20.9921,
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.0413,
            'dropoff_lng' => 19.9362,
            'preferred_date' => '2026-06-15',
            'distance_km' => 295.50,
            'duration_seconds' => 13_500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50,
            'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02,
            'minimum_adjustment' => 0,
            'net_total' => 1377.77,
            'vat_rate' => 23.00,
            'vat_amount' => 316.89,
            'gross_total' => 1694.66,
        ]);

        $fresh = $quote->fresh();

        $this->assertSame(QuoteStatus::Draft, $fresh->status);
        $this->assertSame('OF/2026/05/0001', $fresh->number);
        $this->assertSame('1377.77', (string) $fresh->net_total);
        $this->assertSame('PLN', $fresh->currency);
        $this->assertEqualsWithDelta(52.2818, $fresh->pickup_lat, 0.0001);
        $this->assertTrue($fresh->loaded);              // default
        $this->assertFalse($fresh->round_trip);
    }

    public function test_status_enum_cast_works(): void
    {
        $quote = Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/0002',
            'status' => 'sent',
            'customer_name' => 'X',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 0, 'duration_seconds' => 0,
            'routing_provider' => 'ors',
            'rate_per_km' => 0, 'base_cost' => 0, 'net_total' => 0,
            'vat_rate' => 23, 'vat_amount' => 0, 'gross_total' => 0,
        ]);

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertSame('info', $quote->status->color());
    }

    public function test_quote_status_is_final_helper(): void
    {
        $this->assertFalse(QuoteStatus::Draft->isFinal());
        $this->assertFalse(QuoteStatus::Sent->isFinal());
        $this->assertTrue(QuoteStatus::Accepted->isFinal());
        $this->assertTrue(QuoteStatus::Rejected->isFinal());
        $this->assertTrue(QuoteStatus::Expired->isFinal());
        $this->assertTrue(QuoteStatus::Withdrawn->isFinal());
    }

    public function test_unique_number_constraint(): void
    {
        Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/0001',
            'status' => 'draft',
            'customer_name' => 'X',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 0, 'duration_seconds' => 0,
            'routing_provider' => 'ors',
            'rate_per_km' => 0, 'base_cost' => 0, 'net_total' => 0,
            'vat_rate' => 23, 'vat_amount' => 0, 'gross_total' => 0,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/0001',   // duplicate
            'status' => 'draft',
            'customer_name' => 'Y',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 0, 'duration_seconds' => 0,
            'routing_provider' => 'ors',
            'rate_per_km' => 0, 'base_cost' => 0, 'net_total' => 0,
            'vat_rate' => 23, 'vat_amount' => 0, 'gross_total' => 0,
        ]);
    }

    public function test_soft_deletes(): void
    {
        $quote = Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/0010',
            'status' => 'draft',
            'customer_name' => 'X',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 0, 'duration_seconds' => 0,
            'routing_provider' => 'ors',
            'rate_per_km' => 0, 'base_cost' => 0, 'net_total' => 0,
            'vat_rate' => 23, 'vat_amount' => 0, 'gross_total' => 0,
        ]);
        $quote->delete();

        $this->assertNull(Quote::find($quote->id));
        $this->assertNotNull(Quote::withTrashed()->find($quote->id));
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
            $t->json('fixed_fees_snapshot')->nullable();
            $t->decimal('surcharge_percent_snapshot', 5, 2)->nullable();
            $t->decimal('surcharge_amount_snapshot', 10, 2)->nullable();
            $t->decimal('minimum_adjustment', 10, 2)->default(0);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 4, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
            $t->decimal('exchange_rate_to_pln', 10, 4)->nullable();
            $t->date('exchange_rate_date')->nullable();
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
