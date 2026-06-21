<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Models\Central\Invoice as CentralInvoice;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice as TenantInvoice;
use App\Models\Tenant\InvoiceItem;
use App\Services\Invoicing\InvoicePdfGenerator;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR I1 — InvoicePdfGenerator z 2 templatami (tenant branding + Hovera/Sendormeco).
 * Testujemy renderowane HTML (DomPDF input) zamiast binarnego PDF — szybsze
 * + odporne na różnice driver'a (mPDF vs DomPDF).
 */
class InvoicePdfGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hov_pdf_').'.sqlite';
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
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_tenant_invoice_html_contains_seller_buyer_and_total(): void
    {
        $invoice = $this->seedTenantInvoice([
            'number' => 'FV/2026/06/0001',
            'seller_name' => 'Stajnia Wisła',
            'seller_nip' => '5252666777',
            'buyer_name' => 'Anna Kowalska',
            'total_cents' => 12300,
            'currency' => 'PLN',
        ]);

        $html = app(InvoicePdfGenerator::class)->renderTenantHtml($invoice);

        $this->assertStringContainsString('FV/2026/06/0001', $html);
        $this->assertStringContainsString('Stajnia Wisła', $html);
        $this->assertStringContainsString('5252666777', $html);
        $this->assertStringContainsString('Anna Kowalska', $html);
        $this->assertStringContainsString('123,00', $html); // total_cents / 100 z polskim format
        $this->assertStringContainsString('PLN', $html);
    }

    public function test_tenant_invoice_uses_tenant_branding_color(): void
    {
        $tenant = $this->makeTenant();
        $tenant->branding = ['primary_color' => '#FF6B35', 'logo_url' => 'https://example.com/logo.png'];
        $tenant->save();

        $invoice = $this->seedTenantInvoice();
        $html = app(InvoicePdfGenerator::class)->renderTenantHtml($invoice, $tenant);

        $this->assertStringContainsString('#FF6B35', $html);
        $this->assertStringContainsString('https://example.com/logo.png', $html);
    }

    public function test_tenant_invoice_korekta_kind_renders_korygujaca_header(): void
    {
        $invoice = $this->seedTenantInvoice(['kind' => 'fv_korekta']);
        $html = app(InvoicePdfGenerator::class)->renderTenantHtml($invoice);

        $this->assertStringContainsString('FAKTURA KORYGUJĄCA', $html);
    }

    public function test_hovera_invoice_uses_sendormeco_data_from_config(): void
    {
        config()->set('hovera.legal', [
            'company_name' => 'Sendormeco Holding sp. z o.o.',
            'nip' => '5252866457',
            'regon' => '389194801',
            'krs' => '0000906110',
            'address' => 'ul. Złota 75A/7, 00-819 Warszawa',
            'iban' => 'PL12 1234 5678 9012 3456 7890 1234',
            'bank_name' => 'mBank S.A.',
            'court' => 'Sąd Rejonowy dla m.st. Warszawy',
            'support_email' => 'office@hovera.app',
        ]);

        $tenant = $this->makeTenant();
        $invoice = $this->seedCentralInvoice($tenant);

        $html = app(InvoicePdfGenerator::class)->renderCentralHtml($invoice);

        $this->assertStringContainsString('Sendormeco Holding sp. z o.o.', $html);
        $this->assertStringContainsString('5252866457', $html);
        $this->assertStringContainsString('0000906110', $html);
        $this->assertStringContainsString('PL12 1234 5678 9012 3456 7890 1234', $html);
        $this->assertStringContainsString('mBank S.A.', $html);
    }

    public function test_hovera_invoice_omits_payment_block_when_iban_missing(): void
    {
        config()->set('hovera.legal.iban', '');

        $tenant = $this->makeTenant();
        $invoice = $this->seedCentralInvoice($tenant);

        $html = app(InvoicePdfGenerator::class)->renderCentralHtml($invoice);

        $this->assertStringNotContainsString('Dane do przelewu', $html);
    }

    public function test_hovera_invoice_shows_brand_strip(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->seedCentralInvoice($tenant);

        $html = app(InvoicePdfGenerator::class)->renderCentralHtml($invoice);

        $this->assertStringContainsString('hovera.app', $html);
        $this->assertStringContainsString('FAKTURA VAT', $html);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $tenant = new Tenant([
            'slug' => 'test-'.$u,
            'name' => 'Test Tenant '.$u,
            'legal_name' => 'Test Tenant Sp. z o.o.',
            'tax_id' => '1234567890',
            'db_name' => 'db_'.$u,
            'db_username' => 'user_'.$u,
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        return $tenant;
    }

    private function seedTenantInvoice(array $overrides = []): TenantInvoice
    {
        $invoice = TenantInvoice::create(array_merge([
            'number' => 'FV/TEST/'.uniqid(),
            'kind' => 'fv',
            'status' => 'issued',
            'seller_name' => 'Test Stable',
            'seller_country' => 'PL',
            'buyer_name' => 'Test Client',
            'buyer_type' => 'individual',
            'buyer_country' => 'PL',
            'issued_at' => now()->toDateString(),
            'sale_date' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ], $overrides));

        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'position' => 1,
            'name' => 'Lekcja indywidualna 60 min',
            'quantity' => 1,
            'unit' => 'szt.',
            'unit_price_cents' => 10000,
            'net_cents' => 10000,
            'vat_rate' => 23,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ]);

        return $invoice->fresh(['items']);
    }

    private function seedCentralInvoice(Tenant $tenant): CentralInvoice
    {
        return CentralInvoice::create([
            'tenant_id' => $tenant->id,
            'number' => 'HF/2026/06/0001',
            'kind' => 'subscription',
            'plan_code' => 'pro',
            'period' => '2026-06',
            'currency' => 'PLN',
            'amount_cents' => 30000,
            'vat_cents' => 6900,
            'total_cents' => 36900,
            'vat_rate' => 23,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
            'snapshot' => [],
        ]);
    }

    private function bootTenantContext(): void
    {
        // Tenant context konieczny żeby TenantModel używał połączenia 'tenant'.
        // Tworzymy zsynchronizowany przez reflection — nie wykonujemy SQL na central.
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
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
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
