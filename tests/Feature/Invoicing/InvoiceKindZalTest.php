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
 * PR I3 — `InvoiceKind::FvZaliczkowa` (ZAL, faktura zaliczkowa). Multi
 * 1:N relacja do final FV — `final_invoice_id` na ZAL wskazuje fakturę
 * końcową rozliczającą wszystkie powiązane zaliczki.
 *
 * Pokrywa:
 *   - enum case + value + shortLabel
 *   - i18n (pl + en)
 *   - migration: final_invoice_id column + index
 *   - Invoice model relations: finalInvoice() + advances()
 *   - KsefInvoiceXmlBuilder:
 *     - ZAL → RodzajFaktury=ZAL
 *     - Final FV (lub KOR) → <DaneFaZaliczkowej> per linked advance
 *   - JpkFa3Exporter: RodzajFaktury=ZAL
 */
class InvoiceKindZalTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_zal_').'.sqlite';
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
        $this->assertSame('fv_zaliczkowa', InvoiceKind::FvZaliczkowa->value);
        $this->assertSame('ZAL', InvoiceKind::FvZaliczkowa->shortLabel());
        $this->assertGreaterThanOrEqual(5, count(InvoiceKind::options()));
    }

    public function test_enum_label_translated_pl_en(): void
    {
        $this->app->setLocale('pl');
        $this->assertSame('Faktura Zaliczkowa', InvoiceKind::FvZaliczkowa->label());

        $this->app->setLocale('en');
        $this->assertSame('Advance payment invoice', InvoiceKind::FvZaliczkowa->label());
    }

    public function test_final_invoice_id_column_exists(): void
    {
        $columns = Schema::connection('tenant')->getColumnListing('invoices');
        $this->assertContains('final_invoice_id', $columns);
    }

    public function test_invoice_relations_link_advances_to_final(): void
    {
        $finalId = $this->seedInvoice('FV/2026/06/100', InvoiceKind::Fv, 300000);
        $advance1Id = $this->seedInvoice('ZAL/2026/05/001', InvoiceKind::FvZaliczkowa, 100000, finalInvoiceId: $finalId);
        $advance2Id = $this->seedInvoice('ZAL/2026/05/002', InvoiceKind::FvZaliczkowa, 200000, finalInvoiceId: $finalId);

        $final = Invoice::with('advances')->find($finalId);
        $this->assertCount(2, $final->advances);
        $this->assertEqualsCanonicalizing(
            [$advance1Id, $advance2Id],
            $final->advances->pluck('id')->all()
        );

        $advance1 = Invoice::with('finalInvoice')->find($advance1Id);
        $this->assertSame($finalId, $advance1->finalInvoice->id);
    }

    public function test_zal_invoice_emits_zal_rodzaj_faktury(): void
    {
        $zalId = $this->seedInvoice('ZAL/2026/05/001', InvoiceKind::FvZaliczkowa, 100000);
        $zal = Invoice::with('items')->find($zalId);

        $xml = app(KsefInvoiceXmlBuilder::class)->build($zal);

        $this->assertStringContainsString('<RodzajFaktury>ZAL</RodzajFaktury>', $xml);
        // ZAL używa tej samej FA(3) schemy co regular VAT — tylko RodzajFaktury się różni
        $this->assertStringContainsString('<KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>', $xml);
    }

    public function test_final_invoice_emits_dane_fa_zaliczkowej_per_advance(): void
    {
        $finalId = $this->seedInvoice('FV/2026/06/100', InvoiceKind::Fv, 300000, issuedAt: '2026-06-10');
        $this->seedInvoice('ZAL/2026/05/001', InvoiceKind::FvZaliczkowa, 100000, finalInvoiceId: $finalId, issuedAt: '2026-05-15');
        $this->seedInvoice('ZAL/2026/05/002', InvoiceKind::FvZaliczkowa, 200000, finalInvoiceId: $finalId, issuedAt: '2026-05-20');

        $final = Invoice::with(['items', 'advances'])->find($finalId);

        $xml = app(KsefInvoiceXmlBuilder::class)->build($final);

        // Final FV ma RodzajFaktury=VAT (regular)
        $this->assertStringContainsString('<RodzajFaktury>VAT</RodzajFaktury>', $xml);

        // Dwie referencje do zaliczek
        $this->assertSame(2, substr_count($xml, '<DaneFaZaliczkowej>'));
        $this->assertStringContainsString('<NrFaZaliczkowej>ZAL/2026/05/001</NrFaZaliczkowej>', $xml);
        $this->assertStringContainsString('<DataWystFaZaliczkowej>2026-05-15</DataWystFaZaliczkowej>', $xml);
        $this->assertStringContainsString('<KwotaZaliczki>1000.00</KwotaZaliczki>', $xml);
        $this->assertStringContainsString('<NrFaZaliczkowej>ZAL/2026/05/002</NrFaZaliczkowej>', $xml);
        $this->assertStringContainsString('<DataWystFaZaliczkowej>2026-05-20</DataWystFaZaliczkowej>', $xml);
        $this->assertStringContainsString('<KwotaZaliczki>2000.00</KwotaZaliczki>', $xml);
    }

    public function test_final_invoice_without_advances_omits_zaliczkowa_block(): void
    {
        $finalId = $this->seedInvoice('FV/2026/06/200', InvoiceKind::Fv, 100000);
        $final = Invoice::with(['items', 'advances'])->find($finalId);

        $xml = app(KsefInvoiceXmlBuilder::class)->build($final);

        $this->assertStringNotContainsString('<DaneFaZaliczkowej>', $xml);
    }

    public function test_jpk_fa3_emits_zal_for_advance_payment_invoice(): void
    {
        $this->seedInvoice('ZAL/2026/05/001', InvoiceKind::FvZaliczkowa, 100000, issuedAt: '2026-05-15');

        $xml = app(JpkFa3Exporter::class)->exportQuarter($this->stableTenant, 2026, 2);

        $this->assertStringContainsString('<RodzajFaktury>ZAL</RodzajFaktury>', $xml);
    }

    public function test_existing_kinds_still_map_correctly(): void
    {
        $fvId = $this->seedInvoice('FV/001', InvoiceKind::Fv, 100000);
        $korId = $this->seedInvoice('KOR/001', InvoiceKind::FvKorekta, 50000);
        $proId = $this->seedInvoice('PRO/001', InvoiceKind::FvProforma, 80000);
        $uprId = $this->seedInvoice('UPR/001', InvoiceKind::FvUproszczona, 40000);

        $builder = app(KsefInvoiceXmlBuilder::class);

        $this->assertStringContainsString('<RodzajFaktury>VAT</RodzajFaktury>', $builder->build(Invoice::with(['items', 'advances'])->find($fvId)));
        $this->assertStringContainsString('<RodzajFaktury>KOR</RodzajFaktury>', $builder->build(Invoice::with(['items', 'advances'])->find($korId)));
        $this->assertStringContainsString('<RodzajFaktury>PRO</RodzajFaktury>', $builder->build(Invoice::with(['items', 'advances'])->find($proId)));
        $this->assertStringContainsString('<RodzajFaktury>UPR</RodzajFaktury>', $builder->build(Invoice::with(['items', 'advances'])->find($uprId)));
    }

    // ---- HELPERS ----

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'zal-st-'.$u,
            'name' => 'ZAL Stable '.$u,
            'legal_name' => 'ZAL Stable '.$u.' sp. z o.o.',
            'tax_id' => '5252866457',
            'type' => TenantType::Stable,
            'db_name' => 'zal_st_'.$u,
            'db_username' => 'zal_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function seedInvoice(
        string $number,
        InvoiceKind $kind,
        int $totalCents,
        ?string $finalInvoiceId = null,
        string $issuedAt = '2026-05-15',
    ): string {
        $invoiceId = (string) Str::ulid();
        $net = (int) round($totalCents / 1.23);
        $vat = $totalCents - $net;

        DB::connection('tenant')->table('invoices')->insert([
            'id' => $invoiceId,
            'number' => $number,
            'kind' => $kind->value,
            'status' => InvoiceStatus::Issued->value,
            'client_id' => (string) Str::ulid(),
            'final_invoice_id' => $finalInvoiceId,
            'seller_name' => 'Seller sp. z o.o.',
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

        return $invoiceId;
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
