<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR I3a (scaffolding) — `extend_invoices_ksef_columns` migration +
 * Invoice model fillable/casts dla nowych kolumn KSeF. Bez logiki
 * send/poll (ta przyjdzie z `TenantKsefSubmissionService`); tu tylko
 * pewność że schema + model są zgodne i akceptują nowe pola.
 */
class InvoiceKsefColumnsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_invksef_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantSchema();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_new_columns_exist_on_invoices_table(): void
    {
        $columns = Schema::connection('tenant')->getColumnListing('invoices');

        $this->assertContains('ksef_reference_number', $columns);
        $this->assertContains('ksef_submitted_at', $columns);
        $this->assertContains('ksef_accepted_at', $columns);
        $this->assertContains('ksef_xml', $columns);
        $this->assertContains('ksef_error_payload', $columns);
        $this->assertContains('ksef_environment', $columns);
    }

    public function test_invoice_accepts_full_ksef_lifecycle_payload(): void
    {
        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv,
            'status' => InvoiceStatus::Issued,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'Test Stable sp. z o.o.',
            'buyer_name' => 'Klient',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'ksef_status' => 'submitted',
            'ksef_reference_number' => '20260621-AB12-CD34-EF56',
            'ksef_submitted_at' => now()->subMinutes(2),
            'ksef_xml' => '<?xml version="1.0"?><Faktura/>',
            'ksef_environment' => 'prod',
        ]);

        $fresh = $invoice->refresh();

        $this->assertSame('submitted', $fresh->ksef_status);
        $this->assertSame('20260621-AB12-CD34-EF56', $fresh->ksef_reference_number);
        $this->assertNotNull($fresh->ksef_submitted_at);
        $this->assertSame('<?xml version="1.0"?><Faktura/>', $fresh->ksef_xml);
        $this->assertSame('prod', $fresh->ksef_environment);
    }

    public function test_ksef_submitted_at_and_accepted_at_cast_to_datetime(): void
    {
        $submittedAt = now()->subMinutes(10);
        $acceptedAt = now()->subMinute();

        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv,
            'status' => InvoiceStatus::Issued,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'X',
            'buyer_name' => 'Y',
            'currency' => 'PLN',
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'ksef_submitted_at' => $submittedAt,
            'ksef_accepted_at' => $acceptedAt,
        ])->refresh();

        $this->assertEquals($submittedAt->timestamp, $invoice->ksef_submitted_at->timestamp);
        $this->assertEquals($acceptedAt->timestamp, $invoice->ksef_accepted_at->timestamp);
    }

    public function test_ksef_error_payload_cast_to_array(): void
    {
        $payload = [
            'http_status' => 422,
            'code' => 'invalid_nip',
            'message' => 'NIP not registered',
            'received_at' => '2026-06-21T18:00:00Z',
        ];

        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv,
            'status' => InvoiceStatus::Issued,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'X',
            'buyer_name' => 'Y',
            'currency' => 'PLN',
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'ksef_status' => 'rejected',
            'ksef_error_payload' => $payload,
        ])->refresh();

        $this->assertIsArray($invoice->ksef_error_payload);
        $this->assertSame(422, $invoice->ksef_error_payload['http_status']);
        $this->assertSame('invalid_nip', $invoice->ksef_error_payload['code']);
    }

    public function test_ksef_pending_idx_supports_polling_query(): void
    {
        $cutoff = now()->subMinutes(5);

        for ($i = 0; $i < 3; $i++) {
            Invoice::create([
                'id' => (string) Str::ulid(),
                'kind' => InvoiceKind::Fv,
                'status' => InvoiceStatus::Issued,
                'client_id' => (string) Str::ulid(),
                'seller_name' => 'X',
                'buyer_name' => 'Y',
                'currency' => 'PLN',
                'subtotal_cents' => 0,
                'vat_cents' => 0,
                'total_cents' => 0,
                'ksef_status' => 'submitted',
                'ksef_submitted_at' => now()->subMinutes(10),
            ]);
        }

        Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv,
            'status' => InvoiceStatus::Issued,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'X',
            'buyer_name' => 'Y',
            'currency' => 'PLN',
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'ksef_status' => 'submitted',
            'ksef_submitted_at' => now(),
        ]);

        $pendingOlderThan5min = Invoice::query()
            ->where('ksef_status', 'submitted')
            ->where('ksef_submitted_at', '<', $cutoff)
            ->count();

        $this->assertSame(3, $pendingOlderThan5min);
    }

    private function setUpTenantSchema(): void
    {
        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('related_payment_id', 26)->nullable();
            $t->string('related_pass_id', 26)->nullable();
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->string('seller_country', 2)->default('PL');
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->string('buyer_country', 2)->default('PL');
            $t->string('buyer_type', 24)->nullable();
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('email_sent_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 12, 6)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->string('exchange_rate_source', 32)->nullable();
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->string('ksef_reference_number', 191)->nullable();
            $t->timestamp('ksef_submitted_at')->nullable();
            $t->timestamp('ksef_accepted_at')->nullable();
            $t->longText('ksef_xml')->nullable();
            $t->json('ksef_error_payload')->nullable();
            $t->string('ksef_environment', 8)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
            $t->index(['ksef_status', 'ksef_submitted_at'], 'invoices_ksef_pending_idx');
        });
    }
}
