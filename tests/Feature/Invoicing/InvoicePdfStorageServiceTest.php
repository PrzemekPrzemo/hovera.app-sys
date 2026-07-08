<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice as TenantInvoice;
use App\Services\Invoicing\InvoicePdfStorageService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Business decision (owner, 2026-07): hovera.app hosts the tenant invoice
 * PDF locally for the issued year + 1 month grace (issued in year Y →
 * hosted through end of January Y+1), then points customers at KSeF.
 *
 * Mirrors the sqlite tenant-schema bootstrap from `InvoicePdfGeneratorTest`.
 */
class InvoicePdfStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hov_pdf_store_').'.sqlite';
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

    public function test_retention_cutoff_is_end_of_january_of_issued_year_plus_one(): void
    {
        $invoice = $this->seedTenantInvoice(['issued_at' => '2026-01-15']);

        $cutoff = app(InvoicePdfStorageService::class)->retentionCutoff($invoice);

        $this->assertSame('2027-01-31 23:59:59', $cutoff->format('Y-m-d H:i:s'));
        $this->assertSame('Europe/Warsaw', $cutoff->getTimezone()->getName());
    }

    public function test_invoice_issued_in_january_of_current_year_is_within_retention(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Europe/Warsaw'));

        $invoice = $this->seedTenantInvoice(['issued_at' => '2026-01-10']);

        $this->assertTrue(app(InvoicePdfStorageService::class)->isWithinRetention($invoice));
    }

    public function test_invoice_issued_in_december_two_years_ago_is_outside_retention(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Europe/Warsaw'));

        // Issued December 2024 → cutoff end of January 2025, long past.
        $invoice = $this->seedTenantInvoice(['issued_at' => '2024-12-20']);

        $this->assertFalse(app(InvoicePdfStorageService::class)->isWithinRetention($invoice));
    }

    public function test_boundary_just_before_and_just_after_cutoff(): void
    {
        // Invoice issued in 2026 → cutoff is 2027-01-31 23:59:59 Europe/Warsaw.
        $invoice = $this->seedTenantInvoice(['issued_at' => '2026-06-01']);
        $service = app(InvoicePdfStorageService::class);

        Carbon::setTestNow(Carbon::parse('2027-01-28 10:00:00', 'Europe/Warsaw'));
        $this->assertTrue($service->isWithinRetention($invoice));

        Carbon::setTestNow(Carbon::parse('2027-01-31 23:59:59', 'Europe/Warsaw'));
        $this->assertTrue($service->isWithinRetention($invoice));

        Carbon::setTestNow(Carbon::parse('2027-02-01 00:00:01', 'Europe/Warsaw'));
        $this->assertFalse($service->isWithinRetention($invoice));
    }

    public function test_invoice_without_issued_at_is_treated_as_outside_retention(): void
    {
        $invoice = $this->seedTenantInvoice(['issued_at' => null]);

        $this->assertFalse(app(InvoicePdfStorageService::class)->isWithinRetention($invoice));
    }

    public function test_ensure_stored_generates_and_persists_pdf_within_retention(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Europe/Warsaw'));

        $invoice = $this->seedTenantInvoice(['issued_at' => '2026-06-01']);

        $available = app(InvoicePdfStorageService::class)->ensureStored($invoice);

        $this->assertTrue($available);
        $invoice->refresh();
        $this->assertSame('local', $invoice->pdf_disk);
        $this->assertSame("invoices/{$invoice->id}.pdf", $invoice->pdf_path);
        $this->assertNotNull($invoice->pdf_generated_at);
        Storage::disk('local')->assertExists($invoice->pdf_path);
    }

    public function test_ensure_stored_returns_false_and_does_not_generate_outside_retention(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-07-08 12:00:00', 'Europe/Warsaw'));

        $invoice = $this->seedTenantInvoice(['issued_at' => '2024-12-20']);

        $available = app(InvoicePdfStorageService::class)->ensureStored($invoice);

        $this->assertFalse($available);
        $invoice->refresh();
        $this->assertNull($invoice->pdf_path);
        Storage::disk('local')->assertDirectoryEmpty('invoices');
    }

    public function test_ksef_redirect_payload_uses_configured_portal_url(): void
    {
        config()->set('invoicing.ksef.portal_url.production', 'https://ksef.mf.gov.pl');

        $invoice = $this->seedTenantInvoice([
            'ksef_reference_number' => 'REF-123',
            'ksef_environment' => 'production',
        ]);

        $payload = app(InvoicePdfStorageService::class)->ksefRedirectPayload($invoice);

        $this->assertSame([
            'ksef_reference_number' => 'REF-123',
            'ksef_environment' => 'production',
            'ksef_portal_url' => 'https://ksef.mf.gov.pl',
        ], $payload);
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
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::connection('tenant')->create('invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->string('horse_id', 26)->nullable();
            $t->integer('position');
            $t->string('name', 255);
            $t->text('description')->nullable();
            $t->decimal('quantity', 12, 3)->default(1);
            $t->string('unit', 16)->nullable();
            $t->integer('vat_rate')->nullable();
            $t->integer('unit_price_cents')->default(0);
            $t->integer('net_cents');
            $t->integer('vat_cents');
            $t->integer('total_cents');
            $t->timestamps();
        });
    }
}
