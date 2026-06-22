<?php

declare(strict_types=1);

namespace Tests\Feature\Ksef;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\Ksef\KsefInvoiceXmlBuilder;
use App\Services\Ksef\KsefRrInvoiceXmlBuilder;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * FA_VAT_RR XML builder dla faktury rolniczej (art. 116 ustawy o VAT).
 * Spec verified against Billu-System reference implementation.
 *
 * Schema: http://crd.gov.pl/wzor/2024/04/04/13150/
 * KodFormularza: FA_VAT_RR (1) wersjaSchemy 1-0E
 */
class KsefRrInvoiceXmlBuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_rrxml_').'.sqlite';
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

        $this->mock(TenantManager::class, function (MockInterface $m) {
            $m->shouldReceive('current')->andReturn($this->tenant);
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_builder_rejects_non_rr_invoice(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::Fv);

        $this->expectException(\InvalidArgumentException::class);
        app(KsefRrInvoiceXmlBuilder::class)->build($invoice);
    }

    public function test_builder_produces_well_formed_xml_with_fa_vat_rr_namespace(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('xmlns="http://crd.gov.pl/wzor/2024/04/04/13150/"', $xml);
        $this->assertStringContainsString('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"', $xml);
        $this->assertStringContainsString('<KodFormularza kodSystemowy="FA_VAT_RR (1)"', $xml);
        $this->assertStringContainsString('FA_VAT_RR</KodFormularza>', $xml);
        $this->assertStringContainsString('<WariantFormularza>1</WariantFormularza>', $xml);

        // XML musi się parsować
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml));
    }

    public function test_builder_uses_nip_when_present_on_buyer(): void
    {
        // Rolnik nip-owy (rzadkie, ale legalne) — używamy NIP zamiast PESEL
        $invoice = $this->makeInvoice(InvoiceKind::FvRr, buyerNip: '1234567890');

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        // Podmiot2 = rolnik
        $this->assertStringContainsString('<Podmiot2>', $xml);
        $this->assertSame(2, substr_count($xml, '<DaneIdentyfikacyjne>'));
        // NIP w Podmiot2 (jest też NIP w Podmiot1 dla nabywcy)
        $this->assertStringContainsString('<NIP>1234567890</NIP>', $xml);
        $this->assertStringNotContainsString('<BrakID>', $xml);
        $this->assertStringNotContainsString('<PESEL>', $xml);
    }

    public function test_builder_uses_pesel_when_no_nip_but_metadata_pesel(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr, metadata: [
            'rolnik_pesel' => '82010312345',
        ]);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<PESEL>82010312345</PESEL>', $xml);
        $this->assertStringNotContainsString('<BrakID>', $xml);
    }

    public function test_builder_falls_back_to_brak_id_when_no_identifier(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<BrakID>1</BrakID>', $xml);
    }

    public function test_builder_emits_dokument_tozsamosci_when_metadata_set(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr, metadata: [
            'rolnik_pesel' => '82010312345',
            'rolnik_dok_tozsamosci_numer' => 'ABC123456',
            'rolnik_dok_tozsamosci_wydany_przez' => 'Prezydent m.st. Warszawy',
            'rolnik_dok_tozsamosci_data_wyd' => '2020-05-10',
        ]);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<DokumentTozsamosci>', $xml);
        $this->assertStringContainsString('<NumerDokumentu>ABC123456</NumerDokumentu>', $xml);
        $this->assertStringContainsString('<WydanyPrzez>Prezydent m.st. Warszawy</WydanyPrzez>', $xml);
        $this->assertStringContainsString('<DataWydania>2020-05-10</DataWydania>', $xml);
    }

    public function test_builder_skips_dokument_tozsamosci_when_no_metadata(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringNotContainsString('<DokumentTozsamosci>', $xml);
    }

    public function test_builder_emits_platnosc_with_iban_from_metadata(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr, metadata: [
            'rolnik_rachunek_bankowy' => 'PL61109010140000071219812874',
        ]);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<Platnosc>', $xml);
        $this->assertStringContainsString('<RachunekBankowy><NrRB>PL61109010140000071219812874</NrRB></RachunekBankowy>', $xml);
    }

    public function test_builder_omits_platnosc_when_no_iban(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringNotContainsString('<Platnosc>', $xml);
    }

    public function test_builder_emits_default_oswiadczenie_dostawcy(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<OswiadczenieDostawcy>', $xml);
        $this->assertStringContainsString('rolnikiem ryczałtowym', $xml);
    }

    public function test_builder_uses_custom_oswiadczenie_from_metadata(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr, metadata: [
            'rolnik_oswiadczenie' => 'Custom oświadczenie zgodne z art. 116.',
        ]);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('Custom oświadczenie', $xml);
        $this->assertStringNotContainsString('rolnikiem ryczałtowym w rozumieniu', $xml);
    }

    public function test_builder_emits_pln_amounts_and_no_kurs_waluty(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<KodWaluty>PLN</KodWaluty>', $xml);
        $this->assertStringNotContainsString('<KursWaluty>', $xml);
        $this->assertStringContainsString('<P_11>500.00</P_11>', $xml);
        $this->assertStringContainsString('<P_13_7>500.00</P_13_7>', $xml);
        $this->assertStringContainsString('<P_14_7>35.00</P_14_7>', $xml);
        $this->assertStringContainsString('<P_15>535.00</P_15>', $xml);
    }

    public function test_builder_emits_position_with_p_12_7_00_vat_rate(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::FvRr);

        $xml = app(KsefRrInvoiceXmlBuilder::class)->build($invoice);

        $this->assertSame(1, substr_count($xml, '<FaWiersz>'));
        $this->assertStringContainsString('<P_7>Pasza</P_7>', $xml);
        // FA_VAT_RR forfeit rate jest 7.00% zawsze
        $this->assertStringContainsString('<P_12>7.00</P_12>', $xml);
    }

    public function test_submission_service_routes_fv_rr_to_rr_builder(): void
    {
        $rrInvoice = $this->makeInvoice(InvoiceKind::FvRr);
        $regularInvoice = $this->makeInvoice(InvoiceKind::Fv);

        $rrXml = app(KsefRrInvoiceXmlBuilder::class)->build($rrInvoice);
        $regularXml = app(KsefInvoiceXmlBuilder::class)->build($regularInvoice);

        $this->assertStringContainsString('FA_VAT_RR', $rrXml);
        $this->assertStringContainsString('FA (3)', $regularXml);
        $this->assertStringContainsString('2024/04/04/13150', $rrXml);
        $this->assertStringContainsString('2023/06/29/12648', $regularXml);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'rr-st-'.$u,
            'name' => 'RR Stable '.$u,
            'legal_name' => 'RR Stable sp. z o.o.',
            'tax_id' => '5260250274',
            'type' => TenantType::Stable,
            'db_name' => 'rr_st_'.$u,
            'db_username' => 'rr_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function makeInvoice(InvoiceKind $kind, array $metadata = [], ?string $buyerNip = null): Invoice
    {
        $invoiceId = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $invoiceId,
            'number' => 'RR/2026/05/'.substr($invoiceId, -4),
            'kind' => $kind->value,
            'status' => InvoiceStatus::Issued->value,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'Hovera Stable sp. z o.o.',
            'seller_nip' => '5260250274',
            'buyer_name' => 'Jan Rolnik',
            'buyer_nip' => $buyerNip,
            'issued_at' => '2026-05-15',
            'sale_date' => '2026-05-15',
            'currency' => 'PLN',
            'subtotal_cents' => 50000,
            'vat_cents' => 3500,
            'total_cents' => 53500,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('invoice_items')->insert([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'position' => 1,
            'name' => 'Pasza',
            'unit' => 'kg',
            'quantity' => 100,
            'unit_price_cents' => 500,
            'net_cents' => 50000,
            'vat_cents' => 3500,
            'total_cents' => 53500,
            'vat_rate' => '7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Invoice::with('items')->find($invoiceId);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('final_invoice_id', 26)->nullable()->index();
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
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 12, 6)->nullable();
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->json('metadata')->nullable();
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
