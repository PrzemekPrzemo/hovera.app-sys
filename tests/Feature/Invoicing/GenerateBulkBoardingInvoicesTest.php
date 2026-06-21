<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Actions\Invoicing\GenerateBulkBoardingInvoices;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR S1 — bulk monthly boarding invoice. `GenerateBulkBoardingInvoices`
 * action zasilający `/app/bulk-invoicing` page. Per-client Draft invoice
 * per wybrany miesiąc, oparty o aktywne boarding services konia.
 *
 * Daily services × days_in_month. Monthly services billed once. Item =
 * jeden horse-service pair. Date-range filters na pivot (starts_at / ends_at)
 * vs zakres miesiąca.
 */
class GenerateBulkBoardingInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_bulkbill_').'.sqlite';
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

        // Stable context — action zakłada że jesteśmy w tenant DB (queries
        // przez Eloquent connection 'tenant').
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

    public function test_preview_monthly_service_billed_once(): void
    {
        $client = $this->seedClient('Jan Kowalski');
        $horse = $this->seedHorse($client, 'Iskra');
        $service = $this->seedBoardingService('Pensjon stajenny', priceCents: 200000, frequency: 'monthly');
        $this->linkHorseToService($horse, $service, quantity: 1);

        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertCount(1, $preview);
        $row = $preview[0];
        $this->assertSame('Jan Kowalski', $row['client_name']);
        $this->assertCount(1, $row['items']);
        $this->assertSame(200000, $row['items'][0]['net_cents']);
        $this->assertSame((int) round(200000 * 0.23), $row['items'][0]['vat_cents']);
        // 1.00 quantity (monthly = no day multiplier)
        $this->assertSame(1.0, $row['items'][0]['quantity']);
    }

    public function test_preview_daily_service_multiplies_by_days_in_month(): void
    {
        $client = $this->seedClient('Anna Nowak');
        $horse = $this->seedHorse($client, 'Echo');
        $service = $this->seedBoardingService('Boks dzienny', priceCents: 5000, frequency: 'daily');
        $this->linkHorseToService($horse, $service, quantity: 1);

        // Maj 2026 = 31 dni
        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertCount(1, $preview);
        $this->assertSame(31.0, $preview[0]['items'][0]['quantity']);
        $this->assertSame(5000 * 31, $preview[0]['items'][0]['net_cents']);
    }

    public function test_preview_skips_service_ended_before_month(): void
    {
        $client = $this->seedClient('Tomasz Olek');
        $horse = $this->seedHorse($client, 'Burza');
        $service = $this->seedBoardingService('Stary kontrakt', priceCents: 100000);
        $this->linkHorseToService($horse, $service, endsAt: '2026-04-30');

        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertSame([], $preview);
    }

    public function test_preview_skips_service_starting_after_month(): void
    {
        $client = $this->seedClient('Tomasz Olek');
        $horse = $this->seedHorse($client, 'Burza');
        $service = $this->seedBoardingService('Nowy kontrakt', priceCents: 100000);
        $this->linkHorseToService($horse, $service, startsAt: '2026-06-01');

        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertSame([], $preview);
    }

    public function test_preview_excludes_clients_with_no_billable_items(): void
    {
        $clientWith = $this->seedClient('Z fakturą');
        $horse = $this->seedHorse($clientWith, 'Z fakturą');
        $service = $this->seedBoardingService('Pensjon', priceCents: 100000);
        $this->linkHorseToService($horse, $service);

        $this->seedClient('Bez koni');
        $clientWithEmptyHorse = $this->seedClient('Koń bez usług');
        $this->seedHorse($clientWithEmptyHorse, 'Sierota');

        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertCount(1, $preview);
        $this->assertSame('Z fakturą', $preview[0]['client_name']);
    }

    public function test_preview_price_override_takes_precedence(): void
    {
        $client = $this->seedClient('Klient');
        $horse = $this->seedHorse($client, 'Koń');
        $service = $this->seedBoardingService('Pensjon', priceCents: 100000, frequency: 'monthly');
        $this->linkHorseToService($horse, $service, priceOverride: 75000);

        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertSame(75000, $preview[0]['items'][0]['unit_price_cents']);
        $this->assertSame(75000, $preview[0]['items'][0]['net_cents']);
    }

    public function test_preview_skips_inactive_service(): void
    {
        $client = $this->seedClient('Klient');
        $horse = $this->seedHorse($client, 'Koń');
        $service = $this->seedBoardingService('Wycofany', priceCents: 100000, isActive: false);
        $this->linkHorseToService($horse, $service);

        $preview = app(GenerateBulkBoardingInvoices::class)
            ->preview($this->stableTenant, Carbon::create(2026, 5, 1));

        $this->assertSame([], $preview);
    }

    public function test_execute_creates_draft_invoice_for_selected_client(): void
    {
        $client = $this->seedClient('Klient');
        $horse = $this->seedHorse($client, 'Koń');
        $service = $this->seedBoardingService('Pensjon', priceCents: 200000, frequency: 'monthly');
        $this->linkHorseToService($horse, $service);

        $createdIds = app(GenerateBulkBoardingInvoices::class)
            ->execute($this->stableTenant, Carbon::create(2026, 5, 1), [$client]);

        $this->assertCount(1, $createdIds);
        $invoice = Invoice::with('items')->find($createdIds[0]);
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($client, $invoice->client_id);
        $this->assertCount(1, $invoice->items);
        $this->assertSame(200000, $invoice->subtotal_cents);
        $this->assertSame(46000, $invoice->vat_cents);
        $this->assertSame(246000, $invoice->total_cents);
        // sale_date = ostatni dzień okresu
        $this->assertSame('2026-05-31', (string) $invoice->sale_date->toDateString());
    }

    public function test_execute_only_processes_selected_clients(): void
    {
        $clientA = $this->seedClient('A');
        $clientB = $this->seedClient('B');
        foreach ([$clientA, $clientB] as $c) {
            $h = $this->seedHorse($c, 'Koń '.$c);
            $s = $this->seedBoardingService('Pensjon', priceCents: 100000);
            $this->linkHorseToService($h, $s);
        }

        $createdIds = app(GenerateBulkBoardingInvoices::class)
            ->execute($this->stableTenant, Carbon::create(2026, 5, 1), [$clientA]);

        $this->assertCount(1, $createdIds);
        $invoice = Invoice::find($createdIds[0]);
        $this->assertSame($clientA, $invoice->client_id);
        // tylko 1 invoice w sumie w DB
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_execute_skips_client_without_items(): void
    {
        $client = $this->seedClient('Bez usług');
        $this->seedHorse($client, 'Sierota');

        $createdIds = app(GenerateBulkBoardingInvoices::class)
            ->execute($this->stableTenant, Carbon::create(2026, 5, 1), [$client]);

        $this->assertSame([], $createdIds);
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_execute_due_date_uses_tenant_setting(): void
    {
        $this->stableTenant->settings = ['invoicing' => ['payment_due_days' => 30]];
        $this->stableTenant->save();

        $client = $this->seedClient('Klient');
        $horse = $this->seedHorse($client, 'Koń');
        $service = $this->seedBoardingService('Pensjon', priceCents: 100000);
        $this->linkHorseToService($horse, $service);

        Carbon::setTestNow('2026-06-01 10:00:00');

        try {
            $createdIds = app(GenerateBulkBoardingInvoices::class)
                ->execute($this->stableTenant, Carbon::create(2026, 5, 1), [$client]);

            $invoice = Invoice::find($createdIds[0]);
            $this->assertSame('2026-07-01', $invoice->due_at->toDateString());
        } finally {
            Carbon::setTestNow();
        }
    }

    // ---- HELPERS ----

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'bulk-st-'.$u,
            'name' => 'Bulk Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'bulk_st_'.$u,
            'db_username' => 'bulk_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function seedClient(string $name): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $id,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedHorse(string $clientId, string $name): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert([
            'id' => $id,
            'name' => $name,
            'owner_client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedBoardingService(
        string $name,
        int $priceCents,
        string $frequency = 'monthly',
        bool $isActive = true,
    ): string {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('boarding_services')->insert([
            'id' => $id,
            'name' => $name,
            'unit' => 'szt.',
            'frequency' => $frequency,
            'price_cents' => $priceCents,
            'vat_rate' => '23',
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function linkHorseToService(
        string $horseId,
        string $serviceId,
        ?int $priceOverride = null,
        float $quantity = 1,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): void {
        DB::connection('tenant')->table('horse_boarding_services')->insert([
            'horse_id' => $horseId,
            'boarding_service_id' => $serviceId,
            'price_override_cents' => $priceOverride,
            'quantity' => $quantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpStableSchema(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 24)->default('individual');
            $t->string('name', 200);
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 16)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('country', 2)->default('PL');
            $t->string('central_user_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('boarding_services', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->string('unit', 32);
            $t->string('frequency', 16);
            $t->integer('price_cents');
            $t->string('vat_rate', 8)->default('23');
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horse_boarding_services', function ($t) {
            $t->string('horse_id', 26);
            $t->string('boarding_service_id', 26);
            $t->integer('price_override_cents')->nullable();
            $t->decimal('quantity', 10, 2)->default(1);
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->primary(['horse_id', 'boarding_service_id']);
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
            $t->string('seller_country', 2)->default('PL');
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->string('buyer_country', 2)->default('PL');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
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
