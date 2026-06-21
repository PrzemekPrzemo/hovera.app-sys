<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\Ksef\JpkFa3Exporter;
use App\Services\Ksef\KsefInvoiceXmlBuilder;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR I3 — `InvoiceKind::FvUproszczona` (UPR) — faktura uproszczona dla
 * sprzedaży ≤450 PLN brutto (art. 106e ust. 5 pkt 3 ustawy o VAT). Bez
 * automatycznej walidacji limitu (decyzja user'a — operator wie co robi).
 *
 * Pokrywa:
 *   - enum case + shortLabel
 *   - i18n labels (pl + en)
 *   - KsefInvoiceXmlBuilder RodzajFaktury='UPR'
 *   - JpkFa3Exporter RodzajFaktury='UPROSZCZONA' (JPK używa pełnych nazw)
 */
class InvoiceKindUprTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_upr_').'.sqlite';
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

    public function test_enum_case_value_and_short_label(): void
    {
        $this->assertSame('fv_uproszczona', InvoiceKind::FvUproszczona->value);
        $this->assertSame('UPR', InvoiceKind::FvUproszczona->shortLabel());
    }

    public function test_enum_label_is_translated(): void
    {
        $this->app->setLocale('pl');
        $this->assertSame('Faktura Uproszczona', InvoiceKind::FvUproszczona->label());

        $this->app->setLocale('en');
        $this->assertSame('Simplified invoice', InvoiceKind::FvUproszczona->label());
    }

    public function test_enum_options_includes_upr(): void
    {
        $options = InvoiceKind::options();

        $this->assertArrayHasKey('fv_uproszczona', $options);
        $this->assertCount(4, $options);
    }

    public function test_ksef_invoice_xml_builder_emits_upr_rodzaj_faktury(): void
    {
        $invoice = $this->makeIssuedInvoice(InvoiceKind::FvUproszczona);

        $xml = app(KsefInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<RodzajFaktury>UPR</RodzajFaktury>', $xml);
        // UPR używa tego samego FA(3) form code jak VAT (różni się tylko RodzajFaktury)
        $this->assertStringContainsString('<KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>', $xml);
    }

    public function test_jpk_fa3_exporter_emits_uproszczona_rodzaj_faktury(): void
    {
        $this->makeIssuedInvoice(InvoiceKind::FvUproszczona, issuedAt: '2026-05-15');

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        // JPK używa pełnych nazw, KSeF FA(3) używa skrótów
        $this->assertStringContainsString('<RodzajFaktury>UPROSZCZONA</RodzajFaktury>', $xml);
    }

    public function test_existing_kinds_still_map_correctly(): void
    {
        // Regression — sprawdzamy że dodanie UPR nie zmieniło istniejących mapowań.
        $fv = $this->makeIssuedInvoice(InvoiceKind::Fv);
        $korekta = $this->makeIssuedInvoice(InvoiceKind::FvKorekta);
        $proforma = $this->makeIssuedInvoice(InvoiceKind::FvProforma);

        $builder = app(KsefInvoiceXmlBuilder::class);

        $this->assertStringContainsString('<RodzajFaktury>VAT</RodzajFaktury>', $builder->build($fv));
        $this->assertStringContainsString('<RodzajFaktury>KOR</RodzajFaktury>', $builder->build($korekta));
        $this->assertStringContainsString('<RodzajFaktury>PRO</RodzajFaktury>', $builder->build($proforma));
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'upr-st-'.$u,
            'name' => 'UPR Stable '.$u,
            'legal_name' => 'UPR Stable '.$u.' sp. z o.o.',
            'tax_id' => '5252866457',
            'type' => TenantType::Stable,
            'db_name' => 'upr_st_'.$u,
            'db_username' => 'upr_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function makeIssuedInvoice(InvoiceKind $kind, string $issuedAt = '2026-05-15'): Invoice
    {
        $invoiceId = (string) Str::ulid();

        DB::connection('tenant')->table('invoices')->insert([
            'id' => $invoiceId,
            'number' => $kind->shortLabel().'/2026/05/'.substr($invoiceId, -4),
            'kind' => $kind->value,
            'status' => InvoiceStatus::Issued->value,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'Seller sp. z o.o.',
            'seller_nip' => '5252866457',
            'buyer_name' => 'Buyer',
            'buyer_nip' => '1234567890',
            'issued_at' => $issuedAt,
            'sale_date' => $issuedAt,
            'currency' => 'PLN',
            'subtotal_cents' => 30000,
            'vat_cents' => 6900,
            'total_cents' => 36900,
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
            'unit_price_cents' => 30000,
            'net_cents' => 30000,
            'vat_cents' => 6900,
            'total_cents' => 36900,
            'vat_rate' => '23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Invoice::with('items')->find($invoiceId);
    }

    private function setUpStableSchema(): void
    {
        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
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
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 12, 6)->nullable();
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
