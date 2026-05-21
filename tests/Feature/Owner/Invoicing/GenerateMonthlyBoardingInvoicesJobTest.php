<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Jobs\Owner\GenerateMonthlyBoardingInvoicesJob;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
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
 * Pokrywa Faza 3 PR 3.2 — GenerateMonthlyBoardingInvoicesJob.
 *
 * Job iteruje active HorseBoardingAssignment'y, switch'uje na stable DB
 * via TenantManager::execute, generuje draft invoice z items per box +
 * monthly boarding services.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.2".
 */
class GenerateMonthlyBoardingInvoicesJobTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_billjob_').'.sqlite';
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
        $this->owner = User::create([
            'name' => 'Jan Owner',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);

        // Mock TenantManager — wzór z DocumentExpiryNotificationTest /
        // StableHorseSnapshotServiceTest. execute() w job'ie próbowałby
        // skonfigurować connection MySQL'em z Tenant::databaseConnectionConfig
        // co nadpisałoby nasz SQLite.
        $held = null;
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

    public function test_creates_invoice_with_box_and_monthly_services(): void
    {
        $clientId = $this->seedClient($this->owner->id);
        $horseId = $this->seedHorseWithBox(centralHorseId: $registry = $this->seedRegistry(), boxRate: 180000);
        $serviceId = $this->seedBoardingService(name: 'Mineralia', priceCents: 20000, frequency: 'monthly');
        $this->linkHorseToService($horseId, $serviceId, priceOverride: null, quantity: 1);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $this->assertSame(1, Invoice::query()->count());
        $invoice = Invoice::query()->with('items')->first();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame(InvoiceKind::Fv, $invoice->kind);
        $this->assertSame($clientId, $invoice->client_id);
        $this->assertCount(2, $invoice->items);
        // box 180000 + service 20000 = 200000 net + 23% VAT 46000 = 246000
        $this->assertSame(200000, $invoice->subtotal_cents);
        $this->assertSame(46000, $invoice->vat_cents);
        $this->assertSame(246000, $invoice->total_cents);

        // Item ma horse_id snapshot z central_horse_id assignment'u
        $this->assertSame($registry, $invoice->items->first()->horse_id);
    }

    public function test_metadata_records_billing_period_and_source(): void
    {
        $this->seedClient($this->owner->id);
        $this->seedHorseWithBox(centralHorseId: $registry = $this->seedRegistry(), boxRate: 100000);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $invoice = Invoice::query()->first();
        $this->assertSame('2026-05', $invoice->metadata['billing_period']);
        $this->assertSame('auto_boarding', $invoice->metadata['source']);
        $this->assertSame($registry, $invoice->metadata['central_horse_id']);
    }

    public function test_is_idempotent_skips_when_invoice_for_period_exists(): void
    {
        $this->seedClient($this->owner->id);
        $this->seedHorseWithBox(centralHorseId: $registry = $this->seedRegistry(), boxRate: 100000);
        $this->makeActiveBoarding($registry);

        $period = Carbon::create(2026, 5, 1);

        // Pierwszy run — tworzy.
        (new GenerateMonthlyBoardingInvoicesJob($period))->handle(app(TenantManager::class));
        $this->assertSame(1, Invoice::query()->count());

        // Drugi run dla tego samego okresu — nie duplikuje.
        (new GenerateMonthlyBoardingInvoicesJob($period))->handle(app(TenantManager::class));
        $this->assertSame(1, Invoice::query()->count());

        // Inny okres — tworzy.
        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 6, 1)))->handle(app(TenantManager::class));
        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_skips_assignment_without_matching_client(): void
    {
        // Brak Client matching central_user_id — owner nie zlinkowany.
        $registry = $this->seedRegistry();
        $this->seedHorseWithBox(centralHorseId: $registry, boxRate: 100000);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_skips_when_horse_missing_in_stable_db(): void
    {
        // Sync rift — registry + assignment istnieją, ale horse nie ma w stable.
        $this->seedClient($this->owner->id);
        $registry = $this->seedRegistry();
        // NIE seedujemy horse'a.
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_skips_when_no_billable_items_no_box_no_services(): void
    {
        $this->seedClient($this->owner->id);
        $registry = $this->seedRegistry();
        // Horse bez boxa i bez services — brak czego billować.
        $horseId = (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert([
            'id' => $horseId,
            'central_horse_id' => $registry,
            'name' => 'Iskra',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_skips_pending_and_ended_assignments(): void
    {
        $this->seedClient($this->owner->id);
        $registry1 = $this->seedRegistry();
        $registry2 = $this->seedRegistry();
        $this->seedHorseWithBox(centralHorseId: $registry1, boxRate: 100000);
        $this->seedHorseWithBox(centralHorseId: $registry2, boxRate: 100000);

        // Pending — skip.
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry1,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);
        // Ended — skip.
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry2,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_filters_service_by_pivot_starts_at_ends_at(): void
    {
        $this->seedClient($this->owner->id);
        $registry = $this->seedRegistry();
        $horseId = $this->seedHorseWithBox(centralHorseId: $registry, boxRate: 100000);
        $expired = $this->seedBoardingService(name: 'Expired', priceCents: 50000);
        $future = $this->seedBoardingService(name: 'Future', priceCents: 70000);
        $active = $this->seedBoardingService(name: 'Active', priceCents: 30000);
        // Service ended w kwietniu — skip dla maja
        $this->linkHorseToService($horseId, $expired, startsAt: '2026-01-01', endsAt: '2026-04-30');
        // Service zaczyna się w czerwcu — skip dla maja
        $this->linkHorseToService($horseId, $future, startsAt: '2026-06-01');
        // Active overlap z majem
        $this->linkHorseToService($horseId, $active, startsAt: '2026-04-01', endsAt: '2026-08-31');
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $invoice = Invoice::query()->with('items')->first();
        // box 100000 + active 30000 = 130000
        $this->assertSame(130000, $invoice->subtotal_cents);
        // 2 items: box + Active (Expired i Future skipped)
        $this->assertCount(2, $invoice->items);
    }

    public function test_uses_pivot_price_override_when_present(): void
    {
        $this->seedClient($this->owner->id);
        $registry = $this->seedRegistry();
        $horseId = $this->seedHorseWithBox(centralHorseId: $registry, boxRate: 100000);
        $service = $this->seedBoardingService(name: 'Pensjonat', priceCents: 200000);
        // Owner ma negocjowaną stawkę 180000 zamiast default 200000.
        $this->linkHorseToService($horseId, $service, priceOverride: 180000);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $invoice = Invoice::query()->with('items')->first();
        // box 100000 + override 180000 = 280000
        $this->assertSame(280000, $invoice->subtotal_cents);
    }

    public function test_skips_non_monthly_frequency_services(): void
    {
        $this->seedClient($this->owner->id);
        $registry = $this->seedRegistry();
        $horseId = $this->seedHorseWithBox(centralHorseId: $registry, boxRate: 100000);
        // Daily service — skip (per-day liczone osobno, nie w monthly run)
        $daily = $this->seedBoardingService(name: 'Owies', priceCents: 500, frequency: 'daily');
        $this->linkHorseToService($horseId, $daily, quantity: 2);
        $monthly = $this->seedBoardingService(name: 'Mineralia', priceCents: 30000, frequency: 'monthly');
        $this->linkHorseToService($horseId, $monthly);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1)))
            ->handle(app(TenantManager::class));

        $invoice = Invoice::query()->with('items')->first();
        // box 100000 + monthly 30000 = 130000 (daily skipped)
        $this->assertSame(130000, $invoice->subtotal_cents);
        $this->assertCount(2, $invoice->items);
    }

    public function test_period_defaults_to_previous_month(): void
    {
        // Bez parametru — job billuje POPRZEDNI miesiąc (uruchomiony 1.
        // czerwca billuje maj).
        Carbon::setTestNow(Carbon::create(2026, 6, 1, 2, 0, 0));
        $this->seedClient($this->owner->id);
        $this->seedHorseWithBox(centralHorseId: $registry = $this->seedRegistry(), boxRate: 100000);
        $this->makeActiveBoarding($registry);

        (new GenerateMonthlyBoardingInvoicesJob)->handle(app(TenantManager::class));

        $invoice = Invoice::query()->first();
        $this->assertSame('2026-05', $invoice->metadata['billing_period']);
        Carbon::setTestNow();
    }

    public function test_unique_id_is_per_period(): void
    {
        // Bez tego dwa runs dla różnych okresów byłyby skip'owane przez
        // ShouldBeUnique queue contract.
        $may = new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 5, 1));
        $june = new GenerateMonthlyBoardingInvoicesJob(Carbon::create(2026, 6, 1));

        $this->assertNotSame($may->uniqueId(), $june->uniqueId());
        $this->assertSame('autobilling:2026-05', $may->uniqueId());
        $this->assertSame('autobilling:2026-06', $june->uniqueId());
    }

    // ---- HELPERS ----

    private function seedRegistry(): string
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);

        return $registry->id;
    }

    private function makeActiveBoarding(string $centralHorseId): HorseBoardingAssignment
    {
        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subMonths(3),
        ]);
    }

    private function seedClient(string $centralUserId): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $id,
            'name' => 'Jan Owner',
            'central_user_id' => $centralUserId,
            'tax_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedHorseWithBox(string $centralHorseId, int $boxRate): string
    {
        $boxId = (string) Str::ulid();
        DB::connection('tenant')->table('boxes')->insert([
            'id' => $boxId,
            'name' => 'Box',
            'type' => 'indoor',
            'capacity' => 1,
            'monthly_rate_cents' => $boxRate,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $horseId = (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert([
            'id' => $horseId,
            'central_horse_id' => $centralHorseId,
            'name' => 'Iskra',
            'box_id' => $boxId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $horseId;
    }

    private function seedBoardingService(string $name, int $priceCents, string $frequency = 'monthly'): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('boarding_services')->insert([
            'id' => $id,
            'name' => $name,
            'unit' => 'm-c',
            'frequency' => $frequency,
            'price_cents' => $priceCents,
            'vat_rate' => '23',
            'is_active' => 1,
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

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'bj-st-'.$u,
            'name' => 'BillJob Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'bj_st_'.$u,
            'db_username' => 'bj_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
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
            $t->string('central_user_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('boxes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('building_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('type', 32)->default('indoor');
            $t->integer('capacity')->default(1);
            $t->integer('monthly_rate_cents')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 32)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 24)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->string('cover_image_path', 500)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->string('livejumping_profile_url', 500)->nullable();
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
