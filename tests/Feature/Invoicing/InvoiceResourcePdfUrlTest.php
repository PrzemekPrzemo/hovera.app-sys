<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Http\Resources\V1\InvoiceResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice as TenantInvoice;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `InvoiceResource::pdf_url` must reflect whether the invoice is within the
 * hosting retention window, NOT whether we've already cached a copy on
 * disk — the PDF is generated lazily on first hit of the `/pdf` endpoint
 * (`InvoicePdfStorageService::ensureStored`), so gating on `pdf_path` being
 * already set would mean the link never appears for an invoice nobody has
 * opened yet. Mirrors the sqlite tenant-schema bootstrap from
 * `InvoicePdfStorageServiceTest`.
 */
class InvoiceResourcePdfUrlTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hov_res_pdf_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');
        $this->setUpTenantTables();
        $this->bootTenantContext();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_pdf_url_present_within_retention_even_without_cached_file(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Europe/Warsaw'));

        $invoice = $this->seedTenantInvoice(['issued_at' => '2026-06-01']);
        $this->assertNull($invoice->pdf_path);

        $array = (new InvoiceResource($invoice))->toArray(Request::create('/'));

        $this->assertNotNull($array['pdf_url']);
        $this->assertStringContainsString((string) $invoice->id, $array['pdf_url']);
    }

    public function test_pdf_url_absent_outside_retention(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Europe/Warsaw'));

        // Issued December 2024 → cutoff end of January 2025, long past.
        $invoice = $this->seedTenantInvoice(['issued_at' => '2024-12-20']);

        $array = (new InvoiceResource($invoice))->toArray(Request::create('/'));

        $this->assertNull($array['pdf_url']);
    }

    private function seedTenantInvoice(array $overrides = []): TenantInvoice
    {
        return TenantInvoice::create(array_merge([
            'number' => 'FV/TEST/'.uniqid(),
            'kind' => 'fv',
            'status' => 'issued',
            'seller_name' => 'Test Stable',
            'seller_country' => 'PL',
            'buyer_name' => 'Test Client',
            'buyer_country' => 'PL',
            'issued_at' => now()->toDateString(),
            'sale_date' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ], $overrides));
    }

    private function bootTenantContext(): void
    {
        $tenant = new Tenant([
            'slug' => 'ctx-'.uniqid(),
            'name' => 'Context Tenant',
            'db_name' => 'ctx_'.uniqid(),
            'db_username' => 'ctx_'.uniqid(),
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26)->nullable();
            $t->string('related_payment_id', 26)->nullable();
            $t->string('related_pass_id', 26)->nullable();
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('final_invoice_id', 26)->nullable();
            $t->string('seller_name');
            $t->string('seller_nip')->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code')->nullable();
            $t->string('seller_city')->nullable();
            $t->string('seller_country', 2)->nullable();
            $t->string('buyer_name');
            $t->string('buyer_nip')->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code')->nullable();
            $t->string('buyer_city')->nullable();
            $t->string('buyer_country', 2)->nullable();
            $t->string('buyer_type', 16)->default('individual');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('email_sent_at')->nullable();
            $t->string('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 12, 6)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->string('exchange_rate_source')->nullable();
            $t->integer('subtotal_cents')->default(0);
            $t->integer('vat_cents')->default(0);
            $t->integer('total_cents')->default(0);
            $t->string('ksef_status', 16)->nullable();
            $t->string('ksef_reference', 64)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->string('ksef_reference_number', 64)->nullable();
            $t->timestamp('ksef_submitted_at')->nullable();
            $t->timestamp('ksef_accepted_at')->nullable();
            $t->text('ksef_xml')->nullable();
            $t->json('ksef_error_payload')->nullable();
            $t->string('ksef_environment', 16)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->string('pdf_disk', 32)->nullable();
            $t->string('pdf_path', 191)->nullable();
            $t->timestamp('pdf_generated_at')->nullable();
            $t->unsignedBigInteger('sync_version')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
    }
}
