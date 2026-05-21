<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Horses;

use App\Models\Tenant\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Faza 3 PR 3.1 — nowa kolumna `invoice_items.horse_id` jako
 * soft FK do `central_horse_registry`. Pozwala filtrować faktury per
 * koń w Owner panel'u (Faza 3.4) i auto-billing job snapshot'uje tu
 * central_horse_id przy generowaniu draft invoice'u (Faza 3.2).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3".
 */
class InvoiceItemHorseLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_iihl_').'.sqlite';
        touch($this->stableDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpInvoicingSchema();
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_horse_id_is_fillable_and_persists(): void
    {
        $invoiceId = $this->seedInvoice();
        $centralHorseId = (string) Str::ulid();

        $item = InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'horse_id' => $centralHorseId,
            'position' => 1,
            'name' => 'Pensjonat — Iskra',
            'quantity' => 1,
            'unit' => 'm-c',
            'vat_rate' => '23',
            'unit_price_cents' => 180000,
            'net_cents' => 180000,
            'vat_cents' => 41400,
            'total_cents' => 221400,
        ]);

        $this->assertSame($centralHorseId, $item->fresh()->horse_id);
    }

    public function test_horse_id_is_nullable_for_backward_compat(): void
    {
        $invoiceId = $this->seedInvoice();

        // Istniejące faktury wystawione przed Fazą 3 nie mają per-horse linka.
        $item = InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'position' => 1,
            'name' => 'Konsultacja',
            'quantity' => 1,
            'unit' => 'godz.',
            'vat_rate' => '23',
            'unit_price_cents' => 10000,
            'net_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ]);

        $this->assertNull($item->fresh()->horse_id);
    }

    public function test_for_horse_scope_filters_by_central_horse_id(): void
    {
        $invoiceId = $this->seedInvoice();
        $horseAId = (string) Str::ulid();
        $horseBId = (string) Str::ulid();

        $this->seedItem($invoiceId, $horseAId, 'Pensjonat — Iskra');
        $this->seedItem($invoiceId, $horseAId, 'Owies — Iskra');
        $this->seedItem($invoiceId, $horseBId, 'Pensjonat — Burza');
        $this->seedItem($invoiceId, null, 'Konsultacja ogólna');

        $itemsForA = InvoiceItem::query()->forHorse($horseAId)->get();
        $itemsForB = InvoiceItem::query()->forHorse($horseBId)->get();

        $this->assertCount(2, $itemsForA);
        $this->assertCount(1, $itemsForB);
        $this->assertSame('Pensjonat — Burza', $itemsForB->first()->name);
    }

    public function test_horse_id_index_exists_for_query_performance(): void
    {
        // Indeks na horse_id jest krytyczny — Owner panel będzie filtrował
        // setki faktur dla setek koni. SQLite via PRAGMA index_list daje
        // nam listę indeksów; sprawdzamy że horse_id jest jednym z nich.
        $indexes = DB::connection('tenant')
            ->select("PRAGMA index_list('invoice_items')");

        $hasHorseIdIndex = false;
        foreach ($indexes as $idx) {
            $cols = DB::connection('tenant')
                ->select("PRAGMA index_info('{$idx->name}')");
            foreach ($cols as $col) {
                if ($col->name === 'horse_id') {
                    $hasHorseIdIndex = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($hasHorseIdIndex, 'horse_id powinien mieć index');
    }

    private function seedInvoice(): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $id,
            'kind' => 'fv',
            'status' => 'draft',
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'Stable Sp. z o.o.',
            'buyer_name' => 'Jan Owner',
            'currency' => 'PLN',
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedItem(string $invoiceId, ?string $horseId, string $name): void
    {
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'horse_id' => $horseId,
            'position' => 1,
            'name' => $name,
            'quantity' => 1,
            'unit' => 'm-c',
            'vat_rate' => '23',
            'unit_price_cents' => 100000,
            'net_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
        ]);
    }

    private function setUpInvoicingSchema(): void
    {
        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('seller_name');
            $t->string('buyer_name');
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
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
