<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Invoices\IssueTransportInvoiceFromQuote;
use App\Domain\Transport\Invoices\TransportInvoiceNumberGenerator;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransportInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_inv_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
        $this->setUpInvoicesTables();
        $this->tenant = $this->makeTenant();

        // Mock TenantManager
        $held = $this->tenant;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$held) {
                if ($held === null) {
                    throw new \RuntimeException('No tenant');
                }

                return $held;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_number_generator_produces_ft_yyyy_mm_nnnn(): void
    {
        $gen = new TransportInvoiceNumberGenerator();
        $issue = Carbon::create(2026, 5, 18);

        $this->assertSame('FT/2026/05/0001', $gen->next(TransportInvoiceKind::Fv, $issue));
        $this->assertSame('FT/2026/05/0002', $gen->next(TransportInvoiceKind::Fv, $issue));
    }

    public function test_number_generator_uses_different_scope_per_kind(): void
    {
        $gen = new TransportInvoiceNumberGenerator();
        $issue = Carbon::create(2026, 5, 18);

        $this->assertSame('FT/2026/05/0001', $gen->next(TransportInvoiceKind::Fv, $issue));
        $this->assertSame('PRO/2026/05/0001', $gen->next(TransportInvoiceKind::Proforma, $issue));
        $this->assertSame('KOR/2026/05/0001', $gen->next(TransportInvoiceKind::Korekta, $issue));
        $this->assertSame('FT/2026/05/0002', $gen->next(TransportInvoiceKind::Fv, $issue));
    }

    public function test_issue_from_accepted_quote_creates_invoice_with_snapshot(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted);

        $invoice = $this->app->make(IssueTransportInvoiceFromQuote::class)
            ->execute($quote, paymentTermsDays: 14, issueDate: Carbon::create(2026, 5, 18));

        $this->assertSame(TransportInvoiceStatus::Issued, $invoice->status);
        $this->assertSame(TransportInvoiceKind::Fv, $invoice->kind);
        $this->assertSame('FT/2026/05/0001', $invoice->number);

        // Seller snapshot z tenant'a
        $this->assertSame('Firma Transport Sp. z o.o.', $invoice->seller_name);
        $this->assertSame('1234567890', $invoice->seller_nip);

        // Buyer z quote
        $this->assertSame($quote->customer_name, $invoice->buyer_name);
        $this->assertSame($quote->customer_email, $invoice->buyer_email);

        // Route snapshot
        $this->assertSame($quote->pickup_address, $invoice->pickup_address);
        $this->assertSame($quote->dropoff_address, $invoice->dropoff_address);
        $this->assertEquals($quote->distance_km, $invoice->distance_km);

        // Daty
        $this->assertSame('2026-05-18', $invoice->issued_at->toDateString());
        $this->assertSame('2026-06-01', $invoice->due_at->toDateString());

        // Quote link
        $this->assertSame($quote->id, $invoice->quote_id);
    }

    public function test_issue_creates_two_line_items_for_quote_with_fuel_surcharge(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02,
            'minimum_adjustment' => 0,
            'net_total' => 1377.77,
            'vat_rate' => 23,
            'vat_amount' => 316.89,
            'gross_total' => 1694.66,
        ]);

        $invoice = $this->app->make(IssueTransportInvoiceFromQuote::class)->execute($quote);

        $items = $invoice->items;
        $this->assertCount(2, $items);
        $this->assertSame('Usługa transportowa', $items[0]->name);
        $this->assertSame(132975, $items[0]->net_cents);
        $this->assertSame('Dopłata paliwowa', $items[1]->name);
        $this->assertSame(4802, $items[1]->net_cents);

        // Totals na invoice odzwierciedlają sumę items (VAT zaokrąglany per item).
        $this->assertSame(132975 + 4802, $invoice->subtotal_cents);
        $expectedVat = (int) round(132975 * 0.23) + (int) round(4802 * 0.23);
        $this->assertSame($expectedVat, $invoice->vat_cents);
    }

    public function test_issue_creates_single_item_when_no_fuel_surcharge(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'base_cost' => 800,
            'fuel_surcharge' => 0,
            'minimum_adjustment' => 0,
        ]);

        $invoice = $this->app->make(IssueTransportInvoiceFromQuote::class)->execute($quote);

        $this->assertCount(1, $invoice->items);
    }

    public function test_issue_blocks_non_accepted_quote(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Sent);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('zaakceptowanej oferty');

        $this->app->make(IssueTransportInvoiceFromQuote::class)->execute($quote);
    }

    public function test_issue_blocks_when_invoice_already_exists_for_quote(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted);
        $service = $this->app->make(IssueTransportInvoiceFromQuote::class);

        $first = $service->execute($quote);
        $this->assertNotNull($first->number);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('już istnieje');

        $service->execute($quote);
    }

    public function test_issue_includes_minimum_adjustment_in_service_line_item(): void
    {
        // Gdy quote.minimum_adjustment > 0 (np. trasa 100km × 4.50 = 450, ale min 800),
        // service item ma cenę = base_cost + minimum_adjustment (czyli 800).
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'base_cost' => 450,
            'minimum_adjustment' => 350,
            'fuel_surcharge' => 0,
        ]);

        $invoice = $this->app->make(IssueTransportInvoiceFromQuote::class)->execute($quote);

        $this->assertSame(80000, $invoice->items->first()->net_cents);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma Transport',
            'legal_name' => 'Firma Transport Sp. z o.o.',
            'tax_id' => '1234567890',
            'type' => TenantType::Transporter,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'country' => 'PL',
            'branding' => [
                'address' => 'ul. Testowa 1',
                'postal_code' => '00-001',
                'city' => 'Warszawa',
                'iban' => 'PL00 0000 0000 0000 0000 0000 0000',
                'bank_name' => 'mBank',
            ],
        ]);
    }

    private function makeQuote(QuoteStatus $status, array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'customer_company' => null,
            'customer_tax_id' => null,
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.2818, 'pickup_lng' => 20.9921,
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.0413, 'dropoff_lng' => 19.9362,
            'preferred_date' => '2026-06-15',
            'distance_km' => 295.50, 'duration_seconds' => 13_500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50, 'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02, 'minimum_adjustment' => 0,
            'net_total' => 1377.77, 'vat_rate' => 23,
            'vat_amount' => 316.89, 'gross_total' => 1694.66,
            'currency' => 'PLN',
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

    private function setUpInvoicesTables(): void
    {
        Schema::connection('tenant')->create('transport_invoice_counters', function ($t) {
            $t->string('scope', 64)->primary();
            $t->unsignedInteger('seq')->default(0);
            $t->timestamp('updated_at')->useCurrent();
        });

        Schema::connection('tenant')->create('transport_invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable()->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('quote_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->string('seller_country', 2)->default('PL');
            $t->string('seller_iban', 40)->nullable();
            $t->string('seller_bank_name', 120)->nullable();
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->string('buyer_country', 2)->default('PL');
            $t->string('buyer_email')->nullable();
            $t->string('pickup_address')->nullable();
            $t->string('dropoff_address')->nullable();
            $t->date('service_date')->nullable();
            $t->decimal('distance_km', 8, 2)->nullable();
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->unsignedBigInteger('subtotal_cents')->default(0);
            $t->unsignedBigInteger('vat_cents')->default(0);
            $t->unsignedBigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->string('pdf_url')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('transport_invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
            $t->string('description')->nullable();
            $t->decimal('quantity', 10, 3)->default(1);
            $t->string('unit', 16)->default('usł.');
            $t->string('vat_rate', 8)->default('23');
            $t->unsignedBigInteger('unit_price_cents');
            $t->unsignedBigInteger('net_cents');
            $t->unsignedBigInteger('vat_cents');
            $t->unsignedBigInteger('total_cents');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
