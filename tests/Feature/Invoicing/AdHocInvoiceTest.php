<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\InvoiceResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression guard: po migracji `make_invoice_client_id_nullable` można
 * wystawiać FV dla odbiorcy ad-hoc (bez Client'a w bazie) — wystarczy
 * komplet snapshot fields `buyer_*`. Test też sprawdza że FV z Client'em
 * dalej działa i relacja `->client()` resolvuje obie ścieżki poprawnie.
 */
class AdHocInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

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
        $this->setUpTenantTables();
        $this->bootTenant();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_invoice_can_be_created_without_client_id_when_buyer_snapshot_provided(): void
    {
        $invoice = Invoice::create([
            'number' => null,
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => null,
            'seller_name' => 'Stajnia Bucefał Sp. z o.o.',
            'seller_country' => 'PL',
            'buyer_name' => 'Jan Kowalski',
            'buyer_nip' => '5252344078',
            'buyer_address' => 'ul. Marszałkowska 1',
            'buyer_postal_code' => '00-001',
            'buyer_city' => 'Warszawa',
            'buyer_country' => 'PL',
            'buyer_type' => 'company',
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
            'issued_at' => now()->toDateString(),
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertNull($invoice->client_id);
        $this->assertSame('Jan Kowalski', $invoice->buyer_name);
        $this->assertNull($invoice->client);
    }

    public function test_invoice_still_works_with_existing_client_relation(): void
    {
        $client = Client::create([
            'id' => '01HCLIENT0000000000000ABCD',
            'type' => 'individual',
            'name' => 'Anna Kowalska',
            'email' => 'anna@stable.test',
        ]);

        $invoice = Invoice::create([
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => $client->id,
            'seller_name' => 'Stajnia',
            'buyer_name' => $client->name,
            'buyer_type' => 'individual',
            'currency' => 'PLN',
            'subtotal_cents' => 5000,
            'vat_cents' => 1150,
            'total_cents' => 6150,
            'issued_at' => now()->toDateString(),
        ]);

        $this->assertSame($client->id, $invoice->client_id);
        $this->assertNotNull($invoice->client);
        $this->assertSame('Anna Kowalska', $invoice->client->name);
    }

    public function test_for_client_scope_excludes_ad_hoc_invoices(): void
    {
        $client = Client::create([
            'id' => '01HCLIENT0000000000000XYZ1',
            'type' => 'individual',
            'name' => 'Filtered Client',
        ]);

        Invoice::create($this->payload(clientId: $client->id, buyer: 'Filtered Client'));
        Invoice::create($this->payload(clientId: null, buyer: 'Ad-hoc Buyer'));

        $forClient = Invoice::query()->forClient($client->id)->get();

        $this->assertCount(1, $forClient);
        $this->assertSame('Filtered Client', $forClient->first()->buyer_name);
    }

    public function test_mutate_form_data_clears_dangling_client_id_when_buyer_name_present(): void
    {
        $data = [
            'kind' => 'fv',
            'client_id' => null,
            'buyer_name' => 'Ad-hoc Person',
            'buyer_type' => 'individual',
        ];

        $mutated = InvoiceResource::mutateFormDataBeforeCreate($data);

        $this->assertNull($mutated['client_id']);
        $this->assertSame('draft', $mutated['status']);
        $this->assertSame('PLN', $mutated['currency']);
    }

    private function payload(?string $clientId, string $buyer): array
    {
        return [
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => $clientId,
            'seller_name' => 'Stable',
            'buyer_name' => $buyer,
            'buyer_type' => 'individual',
            'currency' => 'PLN',
            'subtotal_cents' => 1000,
            'vat_cents' => 230,
            'total_cents' => 1230,
            'issued_at' => now()->toDateString(),
        ];
    }

    private function bootTenant(): void
    {
        $tenant = new Tenant([
            'slug' => 'inv-adhoc-test',
            'name' => 'Inv Ad-hoc',
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
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
        Schema::connection('tenant')->create('clients', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 16)->nullable();
            $t->string('city', 120)->nullable();
            $t->char('country', 2)->default('PL');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        // `invoices` z nullable client_id (po naszej migracji).
        Schema::connection('tenant')->create('invoices', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable()->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26)->nullable();   // ← po migracji
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
            $t->string('buyer_type', 16)->default('individual');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 14, 6)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->string('exchange_rate_source', 32)->nullable();
            $t->integer('subtotal_cents')->default(0);
            $t->integer('vat_cents')->default(0);
            $t->integer('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 64)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
