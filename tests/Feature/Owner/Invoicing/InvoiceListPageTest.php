<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Filament\Owner\Pages\InvoiceList;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Filament Page `InvoiceList` — filter UI per koń ownera
 * (`?horse={centralHorseId}` query param). Backend `forHorse()` jest
 * pokryty w OwnerInvoiceFeedServiceTest; tu sprawdzamy wire-up.
 *
 * Pattern z OwnerInvoiceFeedServiceTest — SQLite tenant DB + mock
 * TenantManager.
 */
class InvoiceListPageTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_ilp_').'.sqlite';
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
            'email' => 'jan-ilp-'.uniqid().'@example.test',
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

        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_mount_lists_all_invoices_when_no_filter(): void
    {
        $registry = $this->makeRegistry('Iskra');
        $this->makeActiveBoarding($registry->id);
        $clientId = $this->seedClient($this->owner->id);
        $invoiceA = $this->seedInvoice($clientId, 'FV/A', InvoiceStatus::Issued);
        $invoiceB = $this->seedInvoice($clientId, 'FV/B', InvoiceStatus::Issued);
        $this->seedItem($invoiceA, $registry->id);
        $this->seedItem($invoiceB, (string) Str::ulid());  // inny koń

        $this->actingAs($this->owner);
        $page = new InvoiceList;
        $page->mount();

        $this->assertNull($page->horseFilter);
        $this->assertCount(2, $page->invoices);
        $this->assertSame(['Iskra'], array_values($page->horseOptions));
    }

    public function test_mount_filters_by_horse_query_param(): void
    {
        $iskra = $this->makeRegistry('Iskra');
        $brando = $this->makeRegistry('Brando');
        $this->makeActiveBoarding($iskra->id);
        $this->makeActiveBoarding($brando->id);
        $clientId = $this->seedClient($this->owner->id);
        $invoiceA = $this->seedInvoice($clientId, 'FV/A', InvoiceStatus::Issued);
        $invoiceB = $this->seedInvoice($clientId, 'FV/B', InvoiceStatus::Issued);
        $this->seedItem($invoiceA, $iskra->id);
        $this->seedItem($invoiceB, $brando->id);

        $this->actingAs($this->owner);

        // Symulujemy `?horse={iskra.id}` w URLu.
        Request::merge(['horse' => $iskra->id]);

        $page = new InvoiceList;
        $page->mount();

        $this->assertSame($iskra->id, $page->horseFilter);
        $this->assertCount(1, $page->invoices);
        $this->assertSame('FV/A', $page->invoices->first()->number);
        $this->assertSame('Iskra', $page->activeHorseName());
    }

    public function test_mount_ignores_invalid_horse_filter(): void
    {
        $this->makeRegistry('Iskra');
        $this->actingAs($this->owner);

        // Cudzy koń / nieistniejący — query param ma być zignorowany.
        Request::merge(['horse' => '01HXXXXXXXXXXXXXXXXXXXXXXX']);

        $page = new InvoiceList;
        $page->mount();

        $this->assertNull($page->horseFilter);
        $this->assertNull($page->activeHorseName());
    }

    public function test_filter_url_builds_query_string(): void
    {
        $page = new InvoiceList;

        $allUrl = $page->filterUrl(null);
        $oneUrl = $page->filterUrl('01HZZZZZZZZZZZZZZZZZZZZZZZ');

        // Wystarczy że URL z koniem zawiera query param, a bez konia nie.
        $this->assertStringNotContainsString('?horse=', $allUrl);
        $this->assertStringContainsString('?horse=01HZZZZZZZZZZZZZZZZZZZZZZZ', $oneUrl);
    }

    private function makeRegistry(string $name): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => $name,
        ]);
    }

    private function makeActiveBoarding(string $registryId): HorseBoardingAssignment
    {
        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registryId,
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
            'issued_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedItem(string $invoiceId, string $horseId): void
    {
        DB::connection('tenant')->table('invoice_items')->insert([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'horse_id' => $horseId,
            'position' => 1,
            'name' => 'Pozycja',
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
            'slug' => 'ilp-st-'.$u,
            'name' => 'ILP Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'ilp_st_'.$u,
            'db_username' => 'ilp_st_'.substr($u, -8),
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
