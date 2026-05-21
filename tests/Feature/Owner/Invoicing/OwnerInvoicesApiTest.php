<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Invoicing;

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
 * HTTP tests dla `/api/owner/invoices/*` endpoint'ów. Każdy test
 * `actingAs` owner'a i bije w API z Sanctum SPA mode (session cookie
 * automatycznie).
 */
class OwnerInvoicesApiTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_invapi_').'.sqlite';
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

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/owner/invoices');
        $response->assertUnauthorized();
    }

    public function test_index_returns_owner_invoices(): void
    {
        $this->makeActiveBoarding();
        $clientId = $this->seedClient($this->owner->id);
        $this->seedInvoice($clientId, 'FV/2026/05/0001', InvoiceStatus::Issued);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/invoices');

        $response->assertOk();
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('data.0.number', 'FV/2026/05/0001');
    }

    public function test_index_for_horse_returns_403_when_not_owner(): void
    {
        // Other user owns horse, current user nie ma access.
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $other->id,
            'name' => 'NotMine',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/horses/'.$registry->id.'/invoices');

        $response->assertForbidden();
    }

    public function test_index_for_horse_returns_filtered_invoices(): void
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->makeActiveBoarding($registry->id);
        $clientId = $this->seedClient($this->owner->id);
        $invoiceA = $this->seedInvoice($clientId, 'FV/A', InvoiceStatus::Issued);
        $invoiceB = $this->seedInvoice($clientId, 'FV/B', InvoiceStatus::Issued);
        $this->seedItem($invoiceA, $registry->id);
        $this->seedItem($invoiceB, (string) Str::ulid()); // inny koń

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/horses/'.$registry->id.'/invoices');

        $response->assertOk();
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('data.0.number', 'FV/A');
    }

    public function test_show_returns_full_invoice_detail(): void
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->makeActiveBoarding($registry->id);
        $clientId = $this->seedClient($this->owner->id);
        $invoiceId = $this->seedInvoice($clientId, 'FV/2026/05/0001', InvoiceStatus::Issued);
        $this->seedItem($invoiceId, $registry->id, 'Pensjonat — Iskra');

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/invoices/'.$this->stableTenant->id.'/'.$invoiceId);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id', 'number', 'kind', 'status',
                'seller_name', 'buyer_name',
                'subtotal_cents', 'vat_cents', 'total_cents',
                'items' => [['id', 'name', 'horse_id', 'unit_price_cents']],
            ],
        ]);
        $response->assertJsonPath('data.number', 'FV/2026/05/0001');
        $response->assertJsonPath('data.items.0.name', 'Pensjonat — Iskra');
    }

    public function test_show_returns_404_for_unknown_invoice(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/invoices/'.$this->stableTenant->id.'/'.(string) Str::ulid());

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_draft_invoice(): void
    {
        $this->makeActiveBoarding();
        $clientId = $this->seedClient($this->owner->id);
        $invoiceId = $this->seedInvoice($clientId, null, InvoiceStatus::Draft);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/invoices/'.$this->stableTenant->id.'/'.$invoiceId);

        $response->assertNotFound();
    }

    public function test_show_returns_404_when_owner_not_linked_in_stable(): void
    {
        $this->makeActiveBoarding();
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
        $otherClientId = $this->seedClient($other->id);
        $invoiceId = $this->seedInvoice($otherClientId, 'FV/2026/05/0001', InvoiceStatus::Issued);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/invoices/'.$this->stableTenant->id.'/'.$invoiceId);

        // 404 (nie 403) — celowo nie ujawniamy istnienia faktury.
        $response->assertNotFound();
    }

    public function test_pdf_endpoint_returns_501_placeholder(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/invoices/'.$this->stableTenant->id.'/'.(string) Str::ulid().'/pdf');

        $response->assertStatus(501);
    }

    public function test_pay_endpoint_returns_501_placeholder(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/invoices/'.$this->stableTenant->id.'/'.(string) Str::ulid().'/pay');

        $response->assertStatus(501);
    }

    // ---- HELPERS ----

    private function makeActiveBoarding(?string $centralHorseId = null): void
    {
        $centralHorseId ??= CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ])->id;

        HorseBoardingAssignment::create([
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

    private function seedInvoice(string $clientId, ?string $number, InvoiceStatus $status): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $id,
            'number' => $number,
            'kind' => InvoiceKind::Fv->value,
            'status' => $status->value,
            'client_id' => $clientId,
            'seller_name' => 'Stable',
            'buyer_name' => 'Jan Owner',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'issued_at' => $status !== InvoiceStatus::Draft ? now()->toDateString() : null,
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
            'slug' => 'api-st-'.$u,
            'name' => 'API Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'api_st_'.$u,
            'db_username' => 'api_st_'.substr($u, -8),
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
