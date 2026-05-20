<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Ksef;

use App\Domain\Transport\Ksef\TransporterKsefService;
use App\Enums\TenantType;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\TransportSettings;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KSeF KOR (faktury korygujące) — XML musi zawierać `<RodzajFaktury>KOR`
 * + `<DaneFaKorygowanej>` block z `<NrFaKorygowanej>` i
 * `<DataWystFaKorygowanej>`. Bez tego MF odrzuca FA(3) walidacją serwerową.
 * Patrz docs/TRANSPORT.md §16.
 */
class KsefKorReferenceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_ksefkor_').'.sqlite';
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

        $held = $this->tenant;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$held) {
                if ($held === null) {
                    throw new \RuntimeException('No tenant');
                }

                return $held;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_regular_invoice_xml_contains_vat_kind_no_correction_block(): void
    {
        TransportSettings::current()->update(['ksef_nip' => '1234567890']);

        $invoice = $this->makeInvoice([
            'kind' => TransportInvoiceKind::Fv,
            'number' => 'FT/2026/05/001',
        ]);

        $xml = app(TransporterKsefService::class)->generateXml($invoice);

        $this->assertStringContainsString('<RodzajFaktury>FA</RodzajFaktury>', $xml);
        $this->assertStringNotContainsString('<DaneFaKorygowanej>', $xml);
        $this->assertStringNotContainsString('<NrFaKorygowanej>', $xml);
    }

    public function test_korekta_invoice_xml_contains_kor_kind_and_correction_block(): void
    {
        TransportSettings::current()->update(['ksef_nip' => '1234567890']);

        $original = $this->makeInvoice([
            'kind' => TransportInvoiceKind::Fv,
            'number' => 'FT/2026/05/001',
            'issued_at' => '2026-05-15',
        ]);

        $korekta = $this->makeInvoice([
            'kind' => TransportInvoiceKind::Korekta,
            'number' => 'FT/2026/05/001-KOR-01',
            'corrects_invoice_id' => $original->id,
            'issued_at' => '2026-05-19',
        ]);

        $xml = app(TransporterKsefService::class)->generateXml($korekta);

        // RodzajFaktury=KOR (zamiast FA dla regularnej FV)
        $this->assertStringContainsString('<RodzajFaktury>KOR</RodzajFaktury>', $xml);

        // Reference do oryginalnej FV w bloku `<DaneFaKorygowanej>`
        $this->assertStringContainsString('<DaneFaKorygowanej>', $xml);
        $this->assertStringContainsString('<NrFaKorygowanej>FT/2026/05/001</NrFaKorygowanej>', $xml);
        $this->assertStringContainsString('<DataWystFaKorygowanej>2026-05-15</DataWystFaKorygowanej>', $xml);
        $this->assertStringContainsString('</DaneFaKorygowanej>', $xml);
    }

    public function test_korekta_without_corrects_id_omits_correction_block_gracefully(): void
    {
        // Defensive — gdy admin zapisał Korekta bez wskazania oryginalnej FV
        // (np. legacy data lub bug w form'ie), XML jest generowany bez bloku
        // reference. MF odrzuci, ale code'em nie wybuchamy.
        TransportSettings::current()->update(['ksef_nip' => '1234567890']);

        $invoice = $this->makeInvoice([
            'kind' => TransportInvoiceKind::Korekta,
            'number' => 'FT/2026/05/002-KOR',
            // intentionally no corrects_invoice_id
        ]);

        $xml = app(TransporterKsefService::class)->generateXml($invoice);

        $this->assertStringContainsString('<RodzajFaktury>KOR</RodzajFaktury>', $xml);
        $this->assertStringNotContainsString('<DaneFaKorygowanej>', $xml);
    }

    private function makeInvoice(array $overrides = []): TransportInvoice
    {
        return TransportInvoice::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'FT/2026/05/'.uniqid(),
            'kind' => TransportInvoiceKind::Fv,
            'status' => TransportInvoiceStatus::Issued,
            'seller_name' => 'Firma Transport',
            'seller_nip' => '1234567890',
            'buyer_name' => 'Jan Kowalski',
            'buyer_nip' => '5252111222',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
        ], $overrides));
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma Transport',
            'legal_name' => 'Firma Transport Sp. z o.o.',
            'tax_id' => '1234567890',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'country' => 'PL',
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('extra_horse_fee_default', 8, 2)->default(0);
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            $t->text('ksef_token_encrypted')->nullable();
            $t->string('ksef_environment', 16)->default('test');
            $t->string('ksef_nip', 16)->nullable();
            $t->boolean('ksef_enabled')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });

        Schema::connection('tenant')->create('transport_invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable()->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('quote_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_email')->nullable();
            $t->string('currency', 3)->default('PLN');
            $t->unsignedBigInteger('subtotal_cents')->default(0);
            $t->unsignedBigInteger('vat_cents')->default(0);
            $t->unsignedBigInteger('total_cents')->default(0);
            $t->date('issued_at')->nullable();
            $t->date('due_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->text('ksef_xml')->nullable();
            $t->string('ksef_reference', 80)->nullable();
            $t->string('ksef_status', 32)->nullable();
            $t->timestamp('ksef_submitted_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
