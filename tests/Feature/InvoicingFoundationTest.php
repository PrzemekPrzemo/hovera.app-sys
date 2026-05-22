<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Invoicing\CreateInvoiceCorrection;
use App\Actions\Invoicing\CreateInvoiceFromPass;
use App\Actions\Invoicing\IssueInvoice;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\PassStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Pass;
use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class InvoicingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

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
        $this->tenant = $this->makeTenant();

        $this->client = Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => 'Marek Klient',
            'email' => 'marek@example.com',
            'street' => 'ul. Kwiatowa 5',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'country' => 'PL',
            'tax_id' => '5260250274',
        ]);

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_number_generator_default_template_yearly_reset(): void
    {
        $gen = app(InvoiceNumberGenerator::class);

        $first = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-05-15'));
        $second = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-05-15'));
        $third = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-12-31'));

        $this->assertSame('FV/1/05/2026', $first);
        $this->assertSame('FV/2/05/2026', $second);
        $this->assertSame('FV/3/12/2026', $third); // ten sam rok 2026, kolejny seq

        // Nowy rok → reset
        $newYear = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2027-01-01'));
        $this->assertSame('FV/1/01/2027', $newYear);
    }

    public function test_number_generator_monthly_reset(): void
    {
        $this->tenant->forceFill([
            'settings' => ['invoicing' => ['reset_interval' => 'monthly']],
        ])->save();
        $this->tenant->refresh();

        $gen = app(InvoiceNumberGenerator::class);
        $may1 = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-05-15'));
        $may2 = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-05-20'));
        $jun1 = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-06-01'));

        $this->assertSame('FV/1/05/2026', $may1);
        $this->assertSame('FV/2/05/2026', $may2);
        $this->assertSame('FV/1/06/2026', $jun1); // reset miesięczny
    }

    public function test_number_generator_custom_template_with_padding_and_prefix(): void
    {
        $this->tenant->forceFill([
            'settings' => [
                'invoicing' => [
                    'template' => [
                        'fv' => '{prefix}-{seq:4}/{YYYY}',
                    ],
                    'prefix' => 'STW',
                    'reset_interval' => 'yearly',
                ],
            ],
        ])->save();
        $this->tenant->refresh();

        $gen = app(InvoiceNumberGenerator::class);
        $first = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-03-10'));
        $this->assertSame('STW-0001/2026', $first);
    }

    public function test_number_generator_separate_sequence_per_kind(): void
    {
        $gen = app(InvoiceNumberGenerator::class);
        $fv = $gen->next($this->tenant, InvoiceKind::Fv, Carbon::parse('2026-05-15'));
        $proforma = $gen->next($this->tenant, InvoiceKind::FvProforma, Carbon::parse('2026-05-15'));

        $this->assertSame('FV/1/05/2026', $fv);
        $this->assertSame('PRO/1/05/2026', $proforma);
    }

    public function test_invoice_item_recompute_amounts_with_23_pct_vat(): void
    {
        $invoice = $this->makeDraftInvoice();
        $item = InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'name' => 'Lekcja jeździecka',
            'quantity' => 2,
            'unit' => 'szt.',
            'vat_rate' => '23',
            'unit_price_cents' => 10000, // 100 zł netto za sztukę
            'net_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
        ]);

        $item->recomputeAmounts()->save();

        $this->assertSame(20000, $item->net_cents); // 200 zł netto
        $this->assertSame(4600, $item->vat_cents);  // 46 zł VAT (23%)
        $this->assertSame(24600, $item->total_cents);
    }

    public function test_invoice_item_recompute_zw_vat(): void
    {
        $invoice = $this->makeDraftInvoice();
        $item = InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'name' => 'Pozycja zwolniona',
            'quantity' => 1,
            'unit' => 'szt.',
            'vat_rate' => 'zw',
            'unit_price_cents' => 10000,
            'net_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
        ]);

        $item->recomputeAmounts()->save();

        $this->assertSame(10000, $item->net_cents);
        $this->assertSame(0, $item->vat_cents);
        $this->assertSame(10000, $item->total_cents);
    }

    public function test_issue_assigns_number_and_dates_and_recomputes_totals(): void
    {
        $invoice = $this->makeDraftInvoice();
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'name' => 'Lekcja',
            'quantity' => 1,
            'unit' => 'szt.',
            'vat_rate' => '23',
            'unit_price_cents' => 10000,
            'net_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ]);

        $issued = app(IssueInvoice::class)->execute(
            $invoice->refresh(),
            Carbon::parse('2026-05-15'),
        );

        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertSame('FV/1/05/2026', $issued->number);
        $this->assertSame('2026-05-15', $issued->issued_at->toDateString());
        $this->assertSame('2026-05-15', $issued->sale_date->toDateString());
        $this->assertSame(10000, $issued->subtotal_cents);
        $this->assertSame(2300, $issued->vat_cents);
        $this->assertSame(12300, $issued->total_cents);
    }

    public function test_issue_idempotent_when_already_issued(): void
    {
        $invoice = $this->makeIssuedInvoice('FV/42/01/2026');

        $result = app(IssueInvoice::class)->execute($invoice);

        $this->assertSame('FV/42/01/2026', $result->number); // unchanged
        $this->assertSame(InvoiceStatus::Issued, $result->status);
    }

    public function test_issue_throws_when_no_items(): void
    {
        $invoice = $this->makeDraftInvoice();

        $this->expectException(ValidationException::class);
        app(IssueInvoice::class)->execute($invoice);
    }

    public function test_issue_in_foreign_currency_snapshots_nbp_rate_for_preceding_day(): void
    {
        $invoice = $this->makeDraftInvoice(['currency' => 'EUR']);
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'name' => 'Lekcja',
            'quantity' => 1, 'unit' => 'szt.', 'vat_rate' => '23',
            'unit_price_cents' => 10000, 'net_cents' => 10000,
            'vat_cents' => 2300, 'total_cents' => 12300,
        ]);

        // Wystawienie w czwartek 2026-05-21 → kurs z środy 2026-05-20.
        Http::fake([
            'api.nbp.pl/api/exchangerates/rates/A/EUR/2026-05-20/*' => Http::response([
                'rates' => [['no' => '098/A/NBP/2026', 'effectiveDate' => '2026-05-20', 'mid' => 4.2950]],
            ]),
        ]);

        $issued = app(IssueInvoice::class)->execute(
            $invoice->refresh(),
            Carbon::parse('2026-05-21'),
        );

        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertSame('4.295000', $issued->exchange_rate);
        $this->assertSame('2026-05-20', $issued->exchange_rate_date->toDateString());
        $this->assertSame('nbp_a', $issued->exchange_rate_source);
    }

    public function test_issue_in_pln_does_not_call_nbp_api(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $invoice = $this->makeDraftInvoice(['currency' => 'PLN']);
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'name' => 'Lekcja',
            'quantity' => 1, 'unit' => 'szt.', 'vat_rate' => '23',
            'unit_price_cents' => 10000, 'net_cents' => 10000,
            'vat_cents' => 2300, 'total_cents' => 12300,
        ]);

        $issued = app(IssueInvoice::class)->execute($invoice->refresh());

        // PLN FV nie ma snapshot'u kursu — nullable kolumny zostają null.
        $this->assertNull($issued->exchange_rate);
        $this->assertNull($issued->exchange_rate_date);
        $this->assertNull($issued->exchange_rate_source);
        Http::assertNothingSent();
    }

    public function test_correction_clones_with_negative_amounts(): void
    {
        $original = $this->makeIssuedInvoice('FV/100/05/2026');
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $original->id,
            'name' => 'Lekcja',
            'quantity' => 2,
            'unit' => 'szt.',
            'vat_rate' => '23',
            'unit_price_cents' => 10000,
            'net_cents' => 20000,
            'vat_cents' => 4600,
            'total_cents' => 24600,
        ]);
        $original->load('items')->recomputeTotals()->save();

        $korekta = app(CreateInvoiceCorrection::class)->execute($original->refresh());

        $this->assertSame(InvoiceKind::FvKorekta, $korekta->kind);
        $this->assertSame(InvoiceStatus::Draft, $korekta->status);
        $this->assertSame($original->id, $korekta->corrects_invoice_id);
        $this->assertSame(-20000, $korekta->subtotal_cents);
        $this->assertSame(-4600, $korekta->vat_cents);
        $this->assertSame(-24600, $korekta->total_cents);
        $this->assertCount(1, $korekta->items);

        $item = $korekta->items->first();
        $this->assertSame(-20000, $item->net_cents);
    }

    public function test_correction_throws_when_correcting_correction(): void
    {
        $original = $this->makeIssuedInvoice('FV/1/05/2026');
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $original->id,
            'name' => 'X',
            'quantity' => 1,
            'unit' => 'szt.',
            'vat_rate' => '23',
            'unit_price_cents' => 1000,
            'net_cents' => 1000,
            'vat_cents' => 230,
            'total_cents' => 1230,
        ]);
        $korekta = app(CreateInvoiceCorrection::class)->execute($original->refresh());
        $korekta->forceFill(['status' => InvoiceStatus::Issued->value, 'number' => 'KOR/1/05/2026'])->save();

        $this->expectException(ValidationException::class);
        app(CreateInvoiceCorrection::class)->execute($korekta->refresh());
    }

    public function test_create_invoice_from_pass_auto_issues_with_seller_buyer_snapshot(): void
    {
        $this->tenant->forceFill([
            'name' => 'Stadnina Bucefał',
            'tax_id' => '5260250274',
            'country' => 'PL',
            'settings' => [
                'invoicing' => [
                    'seller_name' => 'Stadnina Bucefał Sp. z o.o.',
                    'seller_nip' => '5260250274',
                    'seller_address' => 'ul. Kasztanowa 7',
                    'seller_postal_code' => '00-001',
                    'seller_city' => 'Warszawa',
                ],
            ],
        ])->save();
        $this->tenant->refresh();

        $pass = Pass::create([
            'id' => '01HPASS00000000000000001',
            'client_id' => $this->client->id,
            'name' => 'Karnet 10x',
            'total_uses' => 10,
            'remaining_uses' => 10,
            'price_cents' => 12300, // 100 zł netto + 23% = 123 zł brutto
            'status' => PassStatus::Active->value,
        ]);

        $invoice = app(CreateInvoiceFromPass::class)->execute($this->tenant, $pass);

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->number);
        $this->assertStringStartsWith('FV/', $invoice->number);
        $this->assertSame($pass->id, $invoice->related_pass_id);

        // Snapshot sprzedawcy (z settings.invoicing)
        $this->assertSame('Stadnina Bucefał Sp. z o.o.', $invoice->seller_name);
        $this->assertSame('5260250274', $invoice->seller_nip);
        $this->assertSame('ul. Kasztanowa 7', $invoice->seller_address);

        // Snapshot nabywcy (z client)
        $this->assertSame('Marek Klient', $invoice->buyer_name);
        $this->assertSame('5260250274', $invoice->buyer_nip);
        $this->assertSame('ul. Kwiatowa 5', $invoice->buyer_address);

        // Pozycja: brutto 12300, VAT 23% → netto 10000, vat 2300
        $item = $invoice->items->first();
        $this->assertSame(10000, $item->net_cents);
        $this->assertSame(2300, $item->vat_cents);
        $this->assertSame(12300, $item->total_cents);
    }

    public function test_create_invoice_from_pass_returns_null_when_pass_has_no_price(): void
    {
        $pass = Pass::create([
            'id' => '01HPASS00000000000000002',
            'client_id' => $this->client->id,
            'name' => 'Free karnet',
            'total_uses' => 5,
            'remaining_uses' => 5,
            'status' => PassStatus::Active->value,
            // brak price_cents (domyślnie 0/null)
        ]);

        $result = app(CreateInvoiceFromPass::class)->execute($this->tenant, $pass);

        $this->assertNull($result);
    }

    public function test_overdue_scope_picks_unpaid_past_due(): void
    {
        $unpaid = $this->makeIssuedInvoice('FV/1/01/2026', dueDate: '2026-01-15');
        $unpaid->forceFill(['due_at' => '2020-01-01'])->save(); // long past due
        $paid = $this->makeIssuedInvoice('FV/2/01/2026');
        $paid->forceFill(['status' => InvoiceStatus::Paid->value, 'due_at' => '2020-01-01'])->save();

        $overdue = Invoice::query()->overdue()->get();
        $this->assertCount(1, $overdue);
        $this->assertSame($unpaid->id, $overdue->first()->id);
    }

    private function makeDraftInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => $this->client->id,
            'seller_name' => 'Sprzedawca',
            'buyer_name' => 'Nabywca',
            'currency' => 'PLN',
        ], $overrides));
    }

    private function makeIssuedInvoice(string $number, ?string $dueDate = null): Invoice
    {
        return Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Issued->value,
            'number' => $number,
            'client_id' => $this->client->id,
            'seller_name' => 'Sprzedawca',
            'buyer_name' => 'Nabywca',
            'currency' => 'PLN',
            'issued_at' => '2026-05-15',
            'sale_date' => '2026-05-15',
            'due_at' => $dueDate,
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'inv-'.$u,
            'name' => 'Invoice Stable',
            'db_name' => 'inv_'.$u,
            'db_username' => 'inv_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('city', 120)->nullable();
            $t->char('country', 2)->default('PL');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('passes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('client_id', 26);
            $t->string('name', 120);
            $t->unsignedSmallInteger('total_uses');
            $t->smallInteger('remaining_uses');
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->unsignedInteger('price_cents')->nullable();
            $t->string('status', 32)->default('active');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoice_counters', function ($t) {
            $t->string('scope', 64)->primary();
            $t->unsignedInteger('seq')->default(0);
            $t->timestamp('updated_at')->useCurrent();
        });

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
            $t->char('seller_country', 2)->default('PL');
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->char('buyer_country', 2)->default('PL');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 14, 6)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->string('exchange_rate_source', 16)->nullable();
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->string('horse_id', 26)->nullable()->index();
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
            $t->string('description')->nullable();
            $t->decimal('quantity', 10, 3)->default(1);
            $t->string('unit', 16)->default('szt.');
            $t->string('vat_rate', 8)->default('23');
            $t->bigInteger('unit_price_cents');
            $t->bigInteger('net_cents');
            $t->bigInteger('vat_cents');
            $t->bigInteger('total_cents');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
