<?php

declare(strict_types=1);

namespace Tests\Feature\Ksef;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `ksef:export-jpk-fa3` command — CLI wrapper dla JpkFa3Exporter.
 *
 * Pokrywa flow: tenant lookup, year/quarter validation, --print stdout
 * output, --disk path resolution.
 */
class KsefExportJpkFa3CommandTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_jpkcli_').'.sqlite';
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

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_command_fails_when_tenant_not_found(): void
    {
        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => 'nonexistent-slug',
            'year' => 2026,
            'quarter' => 2,
        ])->assertExitCode(1);
    }

    public function test_command_fails_when_year_out_of_range(): void
    {
        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => $this->stableTenant->slug,
            'year' => 1999,
            'quarter' => 2,
        ])->assertExitCode(1);
    }

    public function test_command_fails_when_quarter_out_of_range(): void
    {
        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => $this->stableTenant->slug,
            'year' => 2026,
            'quarter' => 5,
        ])->assertExitCode(1);
    }

    public function test_command_saves_quarter_xml_to_default_path(): void
    {
        $this->seedInvoice('FV/2026/05/001', '2026-05-15', 100000);

        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => $this->stableTenant->slug,
            'year' => 2026,
            'quarter' => 2,
        ])->assertExitCode(0);

        $expectedPath = 'jpk/'.$this->stableTenant->slug.'/2026-Q2.xml';
        Storage::disk('local')->assertExists($expectedPath);

        $xml = Storage::disk('local')->get($expectedPath);
        $this->assertStringContainsString('<DataOd>2026-04-01</DataOd>', $xml);
        $this->assertStringContainsString('<DataDo>2026-06-30</DataDo>', $xml);
        $this->assertStringContainsString('<LiczbaFaktur>1</LiczbaFaktur>', $xml);
    }

    public function test_command_saves_full_year_xml_when_quarter_omitted(): void
    {
        $this->seedInvoice('FV/2026/02/001', '2026-02-15', 100000);
        $this->seedInvoice('FV/2026/11/099', '2026-11-30', 200000);

        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => $this->stableTenant->slug,
            'year' => 2026,
        ])->assertExitCode(0);

        $expectedPath = 'jpk/'.$this->stableTenant->slug.'/2026.xml';
        Storage::disk('local')->assertExists($expectedPath);

        $xml = Storage::disk('local')->get($expectedPath);
        $this->assertStringContainsString('<DataOd>2026-01-01</DataOd>', $xml);
        $this->assertStringContainsString('<DataDo>2026-12-31</DataDo>', $xml);
        $this->assertStringContainsString('<LiczbaFaktur>2</LiczbaFaktur>', $xml);
    }

    public function test_command_print_option_streams_xml_to_stdout(): void
    {
        $this->seedInvoice('FV/001', '2026-05-15', 100000);

        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => $this->stableTenant->slug,
            'year' => 2026,
            'quarter' => 2,
            '--print' => true,
        ])->expectsOutputToContain('<KodFormularza kodSystemowy="JPK_FA (3)" wersjaSchemy="1-0">JPK_FA</KodFormularza>')
            ->assertExitCode(0);

        // --print powinno NIE zapisywać do dysku
        Storage::disk('local')->assertMissing('jpk/'.$this->stableTenant->slug.'/2026-Q2.xml');
    }

    public function test_command_custom_path_option_overrides_default(): void
    {
        $this->seedInvoice('FV/001', '2026-05-15', 100000);

        $this->artisan('ksef:export-jpk-fa3', [
            'tenant' => $this->stableTenant->slug,
            'year' => 2026,
            'quarter' => 2,
            '--path' => 'custom/audyt-2026-q2.xml',
        ])->assertExitCode(0);

        Storage::disk('local')->assertExists('custom/audyt-2026-q2.xml');
        // Default path NIE powinien być utworzony
        Storage::disk('local')->assertMissing('jpk/'.$this->stableTenant->slug.'/2026-Q2.xml');
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'jpkcli-'.$u,
            'name' => 'JPK CLI Stable',
            'legal_name' => 'JPK CLI Stable sp. z o.o.',
            'tax_id' => '5252866457',
            'type' => TenantType::Stable,
            'db_name' => 'jpkcli_'.$u,
            'db_username' => 'jpkcli_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function seedInvoice(string $number, string $issuedAt, int $totalCents): void
    {
        $invoiceId = (string) Str::ulid();
        $net = (int) round($totalCents / 1.23);
        $vat = $totalCents - $net;

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
