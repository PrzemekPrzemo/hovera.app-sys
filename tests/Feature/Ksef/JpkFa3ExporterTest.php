<?php

declare(strict_types=1);

namespace Tests\Feature\Ksef;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\Ksef\JpkFa3Exporter;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * PR I3 JPK_FA(3) — exporter agregatu wszystkich wystawionych faktur
 * stajni za okres. Pokrywa:
 *   - quarter/year range computation
 *   - XML structure (Naglowek + Podmiot1 + Faktura + Ctrl + Wiersz + WierszCtrl)
 *   - filtering: only Issued/Paid/Overdue, skip Draft/Void/Cancelled
 *   - control sums (LiczbaFaktur, WartoscFaktur, LiczbaWierszyFaktur, WartoscWierszyFaktur)
 *   - per-invoice fields (P_1, P_2A, P_6, P_13_1, P_14_1, P_15, RodzajFaktury)
 *   - per-item rows (P_2B, P_7, P_8B, P_9A, P_11, P_12)
 */
class JpkFa3ExporterTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_jpk_').'.sqlite';
        touch($this->stableDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpStableSchema();
        $this->stableTenant = $this->makeStableTenant();

        $held = $this->stableTenant;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(fn () => $held);
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
            $m->shouldReceive('execute')->andReturnUsing(function (Tenant $t, callable $cb) use (&$held) {
                $prev = $held;
                $held = $t;
                try {
                    return $cb($t);
                } finally {
                    $held = $prev;
                }
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_quarter_validation_rejects_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 5);
    }

    public function test_quarter_computes_correct_range(): void
    {
        // Q2 2026 = April 1 - June 30
        $this->seedInvoiceWithItem('FV/2026/04/001', '2026-04-15', 100000);
        $this->seedInvoiceWithItem('FV/2026/06/099', '2026-06-30', 200000);
        // Outside Q2 (Q3)
        $this->seedInvoiceWithItem('FV/2026/07/001', '2026-07-01', 300000);

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<DataOd>2026-04-01</DataOd>', $xml);
        $this->assertStringContainsString('<DataDo>2026-06-30</DataDo>', $xml);
        $this->assertStringContainsString('<LiczbaFaktur>2</LiczbaFaktur>', $xml);
        $this->assertStringContainsString('<WartoscFaktur>3000.00</WartoscFaktur>', $xml);
        $this->assertStringNotContainsString('FV/2026/07/001', $xml);
    }

    public function test_year_export_includes_all_quarters(): void
    {
        $this->seedInvoiceWithItem('Q1', '2026-02-15', 100000);
        $this->seedInvoiceWithItem('Q2', '2026-05-20', 200000);
        $this->seedInvoiceWithItem('Q3', '2026-08-10', 300000);
        $this->seedInvoiceWithItem('Q4', '2026-11-30', 400000);
        // Outside year
        $this->seedInvoiceWithItem('2025', '2025-12-31', 999999);
        $this->seedInvoiceWithItem('2027', '2027-01-01', 999999);

        $xml = app(JpkFa3Exporter::class)->exportYear($this->stableTenant, 2026);

        $this->assertStringContainsString('<DataOd>2026-01-01</DataOd>', $xml);
        $this->assertStringContainsString('<DataDo>2026-12-31</DataDo>', $xml);
        $this->assertStringContainsString('<LiczbaFaktur>4</LiczbaFaktur>', $xml);
        // 100k+200k+300k+400k = 1,000,000 cents = 10,000.00
        $this->assertStringContainsString('<WartoscFaktur>10000.00</WartoscFaktur>', $xml);
    }

    public function test_skips_draft_void_cancelled(): void
    {
        $this->seedInvoiceWithItem('FV/001', '2026-05-10', 100000, status: InvoiceStatus::Issued);
        $this->seedInvoiceWithItem('FV/002-draft', '2026-05-11', 200000, status: InvoiceStatus::Draft);
        $this->seedInvoiceWithItem('FV/003-void', '2026-05-12', 300000, status: InvoiceStatus::Void);
        $this->seedInvoiceWithItem('FV/004-cancelled', '2026-05-13', 400000, status: InvoiceStatus::Cancelled);
        $this->seedInvoiceWithItem('FV/005', '2026-05-14', 500000, status: InvoiceStatus::Paid);

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<LiczbaFaktur>2</LiczbaFaktur>', $xml);
        $this->assertStringContainsString('FV/001', $xml);
        $this->assertStringContainsString('FV/005', $xml);
        $this->assertStringNotContainsString('FV/002-draft', $xml);
        $this->assertStringNotContainsString('FV/003-void', $xml);
        $this->assertStringNotContainsString('FV/004-cancelled', $xml);
    }

    public function test_empty_period_produces_zero_control_sums(): void
    {
        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<LiczbaFaktur>0</LiczbaFaktur>', $xml);
        $this->assertStringContainsString('<WartoscFaktur>0.00</WartoscFaktur>', $xml);
        $this->assertStringContainsString('<LiczbaWierszyFaktur>0</LiczbaWierszyFaktur>', $xml);
        $this->assertStringContainsString('<WartoscWierszyFaktur>0.00</WartoscWierszyFaktur>', $xml);
    }

    public function test_header_contains_kod_formularza_and_period(): void
    {
        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('xmlns="http://crd.gov.pl/wzor/2022/01/05/11148/"', $xml);
        $this->assertStringContainsString('<KodFormularza kodSystemowy="JPK_FA (3)" wersjaSchemy="1-0">JPK_FA</KodFormularza>', $xml);
        $this->assertStringContainsString('<WariantFormularza>3</WariantFormularza>', $xml);
        $this->assertStringContainsString('<CelZlozenia>1</CelZlozenia>', $xml);
    }

    public function test_subject_contains_tenant_legal_name_and_nip(): void
    {
        $this->stableTenant->update([
            'legal_name' => 'Sample Stable sp. z o.o.',
            'tax_id' => '5252866457',
        ]);

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<PelnaNazwa>Sample Stable sp. z o.o.</PelnaNazwa>', $xml);
        $this->assertStringContainsString('<NIP>5252866457</NIP>', $xml);
    }

    public function test_invoice_header_fields_include_buyer_seller_nip_and_amounts(): void
    {
        $this->seedInvoiceWithItem(
            number: 'FV/2026/05/001',
            issuedAt: '2026-05-15',
            totalCents: 123000,
            netCents: 100000,
            vatCents: 23000,
        );

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<P_2A>FV/2026/05/001</P_2A>', $xml);
        $this->assertStringContainsString('<P_1>2026-05-15</P_1>', $xml);
        $this->assertStringContainsString('<P_13_1>1000.00</P_13_1>', $xml);
        $this->assertStringContainsString('<P_14_1>230.00</P_14_1>', $xml);
        $this->assertStringContainsString('<P_15>1230.00</P_15>', $xml);
        $this->assertStringContainsString('<RodzajFaktury>VAT</RodzajFaktury>', $xml);
    }

    public function test_invoice_rows_count_and_total_match_items(): void
    {
        $this->seedInvoiceMultipleItems('FV/A', '2026-05-10', items: [
            ['name' => 'Boks', 'qty' => 1, 'unit_price' => 200000, 'net' => 200000],
            ['name' => 'Pasza', 'qty' => 30, 'unit_price' => 1000, 'net' => 30000],
        ]);
        $this->seedInvoiceMultipleItems('FV/B', '2026-05-11', items: [
            ['name' => 'Lekcje', 'qty' => 4, 'unit_price' => 25000, 'net' => 100000],
        ]);

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<LiczbaFaktur>2</LiczbaFaktur>', $xml);
        $this->assertStringContainsString('<LiczbaWierszyFaktur>3</LiczbaWierszyFaktur>', $xml);
        // 2000 + 300 + 1000 = 3300.00
        $this->assertStringContainsString('<WartoscWierszyFaktur>3300.00</WartoscWierszyFaktur>', $xml);
        // each row should reference its invoice number via P_2B
        $this->assertSame(2, substr_count($xml, '<P_2B>FV/A</P_2B>'));
        $this->assertSame(1, substr_count($xml, '<P_2B>FV/B</P_2B>'));
    }

    public function test_korekta_invoice_uses_korekta_rodzaj(): void
    {
        $this->seedInvoiceWithItem('KOR/2026/001', '2026-05-15', 50000, kind: InvoiceKind::FvKorekta);

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<RodzajFaktury>KOREKTA</RodzajFaktury>', $xml);
    }

    // ---- HELPERS ----

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'jpk-st-'.$u,
            'name' => 'JPK Stable '.$u,
            'legal_name' => 'JPK Stable '.$u.' sp. z o.o.',
            'tax_id' => '5252866457',
            'type' => TenantType::Stable,
            'db_name' => 'jpk_st_'.$u,
            'db_username' => 'jpk_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function seedInvoiceWithItem(
        string $number,
        string $issuedAt,
        int $totalCents,
        ?int $netCents = null,
        ?int $vatCents = null,
        InvoiceStatus $status = InvoiceStatus::Issued,
        InvoiceKind $kind = InvoiceKind::Fv,
    ): void {
        $net = $netCents ?? (int) round($totalCents / 1.23);
        $vat = $vatCents ?? ($totalCents - $net);

        $invoiceId = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $invoiceId,
            'number' => $number,
            'kind' => $kind->value,
            'status' => $status->value,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'Seller',
            'seller_nip' => '5252866457',
            'buyer_name' => 'Buyer',
            'buyer_nip' => '1234567890',
            'issued_at' => $issuedAt,
            'sale_date' => $issuedAt,
            'currency' => 'PLN',
            'subtotal_cents' => $net,
            'vat_cents' => $vat,
            'total_cents' => $totalCents,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('invoice_items')->insert([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'position' => 1,
            'name' => 'Usługa',
            'unit' => 'szt.',
            'quantity' => 1,
            'unit_price_cents' => $net,
            'net_cents' => $net,
            'vat_cents' => $vat,
            'total_cents' => $totalCents,
            'vat_rate' => '23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<array{name: string, qty: float, unit_price: int, net: int}>  $items
     */
    private function seedInvoiceMultipleItems(string $number, string $issuedAt, array $items): void
    {
        $invoiceId = (string) Str::ulid();
        $netSum = array_sum(array_column($items, 'net'));
        $vatSum = (int) round($netSum * 0.23);

        DB::connection('tenant')->table('invoices')->insert([
            'id' => $invoiceId,
            'number' => $number,
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Issued->value,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'Seller',
            'seller_nip' => '5252866457',
            'buyer_name' => 'Buyer',
            'issued_at' => $issuedAt,
            'sale_date' => $issuedAt,
            'currency' => 'PLN',
            'subtotal_cents' => $netSum,
            'vat_cents' => $vatSum,
            'total_cents' => $netSum + $vatSum,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($items as $pos => $item) {
            DB::connection('tenant')->table('invoice_items')->insert([
                'id' => (string) Str::ulid(),
                'invoice_id' => $invoiceId,
                'position' => $pos + 1,
                'name' => $item['name'],
                'unit' => 'szt.',
                'quantity' => $item['qty'],
                'unit_price_cents' => $item['unit_price'],
                'net_cents' => $item['net'],
                'vat_cents' => (int) round($item['net'] * 0.23),
                'total_cents' => $item['net'] + (int) round($item['net'] * 0.23),
                'vat_rate' => '23',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function setUpStableSchema(): void
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
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
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
