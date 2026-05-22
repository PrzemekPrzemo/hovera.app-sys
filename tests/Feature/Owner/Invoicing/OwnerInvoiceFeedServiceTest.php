<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Invoicing;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa OwnerInvoiceFeedService — cross-tenant aggregator faktur dla
 * owner panel'u. Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.3".
 */
class OwnerInvoiceFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_feed_').'.sqlite';
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

    public function test_for_owner_returns_issued_invoices_across_stables(): void
    {
        $this->makeActiveBoarding();
        $clientId = $this->seedClient($this->owner->id);
        $this->seedInvoice($clientId, 'FV/2026/05/0001', InvoiceStatus::Issued);
        $this->seedInvoice($clientId, 'FV/2026/04/0007', InvoiceStatus::Paid);

        $result = app(OwnerInvoiceFeedService::class)->forOwner($this->owner);

        $this->assertCount(2, $result);
        $this->assertSame('FV/2026/05/0001', $result->first()->number);
        $this->assertSame($this->stableTenant->id, $result->first()->stableTenantId);
        $this->assertSame($this->stableTenant->name, $result->first()->stableTenantName);
    }

    public function test_for_owner_excludes_draft_invoices(): void
    {
        $this->makeActiveBoarding();
        $clientId = $this->seedClient($this->owner->id);
        $this->seedInvoice($clientId, null, InvoiceStatus::Draft); // draft skipped
        $this->seedInvoice($clientId, 'FV/2026/05/0001', InvoiceStatus::Issued);

        $result = app(OwnerInvoiceFeedService::class)->forOwner($this->owner);

        $this->assertCount(1, $result);
        $this->assertSame('FV/2026/05/0001', $result->first()->number);
    }

    public function test_for_owner_skips_when_no_client_link_in_stable(): void
    {
        $this->makeActiveBoarding();
        // Client istnieje ale central_user_id wskazuje na innego usera.
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
        $otherClientId = $this->seedClient($otherUser->id);
        $this->seedInvoice($otherClientId, 'FV/2026/05/0001', InvoiceStatus::Issued);

        $result = app(OwnerInvoiceFeedService::class)->forOwner($this->owner);

        // Owner nie ma client'a w tej stajni → zwraca pusty.
        $this->assertCount(0, $result);
    }

    public function test_for_horse_filters_by_horse_id_via_items(): void
    {
        $registry = $this->makeRegistry();
        $this->makeActiveBoarding($registry->id);
        $clientId = $this->seedClient($this->owner->id);

        $invoiceA = $this->seedInvoice($clientId, 'FV/A', InvoiceStatus::Issued);
        $invoiceB = $this->seedInvoice($clientId, 'FV/B', InvoiceStatus::Issued);
        $this->seedItem($invoiceA, $registry->id);   // ma horse_id = registry
        $this->seedItem($invoiceB, (string) Str::ulid()); // inny koń

        $result = app(OwnerInvoiceFeedService::class)
            ->forHorse($this->owner, $registry->id);

        $this->assertCount(1, $result);
        $this->assertSame('FV/A', $result->first()->number);
    }

    public function test_for_horse_includes_ended_boarding_history(): void
    {
        // Per roadmap Q3 — ended boarding zachowuje read access do
        // historycznych faktur.
        $registry = $this->makeRegistry();
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);
        $clientId = $this->seedClient($this->owner->id);
        $invoice = $this->seedInvoice($clientId, 'FV/HIST', InvoiceStatus::Paid);
        $this->seedItem($invoice, $registry->id);

        $result = app(OwnerInvoiceFeedService::class)
            ->forHorse($this->owner, $registry->id);

        $this->assertCount(1, $result);
        $this->assertSame('FV/HIST', $result->first()->number);
    }

    public function test_find_invoice_returns_full_detail_with_items(): void
    {
        $registry = $this->makeRegistry();
        $this->makeActiveBoarding($registry->id);
        $clientId = $this->seedClient($this->owner->id);
        $invoiceId = $this->seedInvoice($clientId, 'FV/2026/05/0001', InvoiceStatus::Issued);
        $this->seedItem($invoiceId, $registry->id, 'Pensjonat — Iskra');
        $this->seedItem($invoiceId, null, 'Konsultacja dodatkowa');

        $detail = app(OwnerInvoiceFeedService::class)
            ->findInvoice($this->owner, $this->stableTenant->id, $invoiceId);

        $this->assertNotNull($detail);
        $this->assertSame('FV/2026/05/0001', $detail->number);
        $this->assertCount(2, $detail->items);
        $this->assertSame('Pensjonat — Iskra', $detail->items[0]->name);
        $this->assertSame($registry->id, $detail->items[0]->horseId);
        $this->assertNull($detail->items[1]->horseId);
    }

    public function test_find_invoice_returns_null_for_draft_status(): void
    {
        $this->makeActiveBoarding();
        $clientId = $this->seedClient($this->owner->id);
        $invoiceId = $this->seedInvoice($clientId, null, InvoiceStatus::Draft);

        $detail = app(OwnerInvoiceFeedService::class)
            ->findInvoice($this->owner, $this->stableTenant->id, $invoiceId);

        $this->assertNull($detail);
    }

    public function test_find_invoice_returns_null_when_owner_not_linked_in_stable(): void
    {
        $this->makeActiveBoarding();
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
        $otherClientId = $this->seedClient($otherUser->id);
        $invoiceId = $this->seedInvoice($otherClientId, 'FV/2026/05/0001', InvoiceStatus::Issued);

        // Owner nie ma client'a w tej stajni — feed zwraca null żeby
        // nie ujawnić istnienia faktury.
        $detail = app(OwnerInvoiceFeedService::class)
            ->findInvoice($this->owner, $this->stableTenant->id, $invoiceId);

        $this->assertNull($detail);
    }

    public function test_find_invoice_returns_null_for_unknown_invoice_id(): void
    {
        $this->makeActiveBoarding();
        $this->seedClient($this->owner->id);

        $detail = app(OwnerInvoiceFeedService::class)
            ->findInvoice($this->owner, $this->stableTenant->id, (string) Str::ulid());

        $this->assertNull($detail);
    }

    public function test_summary_extracts_metadata_billing_period_and_horse(): void
    {
        $this->makeActiveBoarding();
        $clientId = $this->seedClient($this->owner->id);
        $invoiceId = $this->seedInvoice($clientId, 'FV/2026/05/0001', InvoiceStatus::Issued, [
            'billing_period' => '2026-05',
            'central_horse_id' => '01HZZZZZZZZZZZZZZZZZZZZZ',
            'horse_name' => 'Iskra',
            'source' => 'auto_boarding',
        ]);

        $result = app(OwnerInvoiceFeedService::class)->forOwner($this->owner);

        $this->assertCount(1, $result);
        $first = $result->first();
        $this->assertSame('2026-05', $first->billingPeriod);
        $this->assertSame('Iskra', $first->horseName);
        $this->assertSame('01HZZZZZZZZZZZZZZZZZZZZZ', $first->centralHorseId);
    }

    public function test_for_owner_includes_ended_boarding_tenants(): void
    {
        // Owner ma ended assignment ale stable wystawiło fakturę w trakcie
        // boarding'u — chcemy żeby owner dalej widział te historyczne FV.
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => (string) Str::ulid(),
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);
        $clientId = $this->seedClient($this->owner->id);
        $this->seedInvoice($clientId, 'FV/HISTORIC', InvoiceStatus::Paid);

        $result = app(OwnerInvoiceFeedService::class)->forOwner($this->owner);

        $this->assertCount(1, $result);
        $this->assertSame('FV/HISTORIC', $result->first()->number);
    }

    // ---- HELPERS ----

    private function makeRegistry(): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
    }

    private function makeActiveBoarding(?string $centralHorseId = null): HorseBoardingAssignment
    {
        $centralHorseId ??= $this->makeRegistry()->id;

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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedInvoice(string $clientId, ?string $number, InvoiceStatus $status, array $metadata = []): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $id,
            'number' => $number,
            'kind' => InvoiceKind::Fv->value,
            'status' => $status->value,
            'client_id' => $clientId,
            'seller_name' => 'Stable Sp. z o.o.',
            'buyer_name' => 'Jan Owner',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'issued_at' => $status !== InvoiceStatus::Draft ? now()->toDateString() : null,
            'metadata' => $metadata !== [] ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedItem(string $invoiceId, ?string $horseId, string $name = 'Pozycja'): void
    {
        DB::connection('tenant')->table('invoice_items')->insert([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'horse_id' => $horseId,
            'position' => 1,
            'name' => $name,
            'quantity' => 1,
            'unit' => 'szt.',
            'vat_rate' => '23',
            'unit_price_cents' => 100000,
            'net_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'feed-st-'.$u,
            'name' => 'Feed Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'feed_st_'.$u,
            'db_username' => 'feed_st_'.substr($u, -8),
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
            $t->string('buyer_type', 16)->default('individual');
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
