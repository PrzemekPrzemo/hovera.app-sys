<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Invoicing;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
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
 * Pokrywa C.7 z OWNER-STABLE-ROADMAP — yearly totals aggregation +
 * forOwnerYear filter dla historii rozliczeń.
 */
class OwnerInvoiceYearlyTotalsTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stable;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hov_yt_').'.sqlite';
        touch($this->stableDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpStableSchema();
        $this->stable = $this->makeStable();
        $this->owner = User::create(['name' => 'Jan', 'email' => 'jan-'.uniqid().'@x.test', 'password' => bcrypt('x')]);

        // active boarding żeby ownerStableTenantIds() zwrócił this stable
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => (string) Str::ulid(),
            'stable_tenant_id' => $this->stable->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subYear(),
        ]);

        $held = null;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
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

    public function test_yearly_totals_aggregates_invoices_by_year(): void
    {
        $client = $this->seedClient();

        $this->seedInvoice($client, '2025-03-15', 100_00);  // 100 PLN
        $this->seedInvoice($client, '2025-08-01', 250_00);  // 250 PLN  (sum 2025 = 350 PLN)
        $this->seedInvoice($client, '2026-01-10', 500_00);  // 500 PLN  (sum 2026 = 500)
        $this->seedInvoice($client, '2026-05-20', 1000_00); // 1000 PLN (sum 2026 = 1500 PLN)

        $totals = app(OwnerInvoiceFeedService::class)->yearlyTotalsForOwner($this->owner);

        $this->assertSame(2, count($totals));
        $this->assertSame(150000, $totals[2026]); // 500 + 1000 = 1500 PLN = 150000 gr
        $this->assertSame(35000, $totals[2025]);  // 100 + 250 = 350 PLN

        // Sortowanie DESC (najnowszy rok pierwszy w iteration)
        $keys = array_keys($totals);
        $this->assertSame(2026, $keys[0]);
    }

    public function test_yearly_totals_skips_draft_invoices(): void
    {
        $client = $this->seedClient();
        $this->seedInvoice($client, '2026-01-10', 500_00, InvoiceStatus::Issued);
        $this->seedInvoice($client, '2026-02-10', 999_99, InvoiceStatus::Draft); // skip
        $this->seedInvoice($client, '2026-03-10', 100_00, InvoiceStatus::Paid);  // include

        $totals = app(OwnerInvoiceFeedService::class)->yearlyTotalsForOwner($this->owner);

        $this->assertSame(60000, $totals[2026]); // 500 + 100 = 600 PLN
    }

    public function test_for_owner_year_filters_to_specific_year(): void
    {
        $client = $this->seedClient();
        $a = $this->seedInvoice($client, '2025-03-15', 100_00);
        $b = $this->seedInvoice($client, '2026-01-10', 500_00);
        $c = $this->seedInvoice($client, '2026-05-20', 1000_00);

        $rows = app(OwnerInvoiceFeedService::class)->forOwnerYear($this->owner, 2026);

        $this->assertSame(2, $rows->count());
        $ids = $rows->pluck('id')->all();
        $this->assertContains($b, $ids);
        $this->assertContains($c, $ids);
        $this->assertNotContains($a, $ids);
    }

    public function test_for_owner_year_returns_empty_when_no_invoices_in_year(): void
    {
        $client = $this->seedClient();
        $this->seedInvoice($client, '2026-01-10', 500_00);

        $rows = app(OwnerInvoiceFeedService::class)->forOwnerYear($this->owner, 2024);
        $this->assertSame(0, $rows->count());
    }

    private function seedClient(): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $id,
            'name' => 'Jan',
            'central_user_id' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedInvoice(string $clientId, string $issuedAt, int $totalCents, InvoiceStatus $status = InvoiceStatus::Issued): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $id,
            'number' => 'FV/'.random_int(10000, 99999),
            'kind' => InvoiceKind::Fv->value,
            'status' => $status->value,
            'client_id' => $clientId,
            'seller_name' => 'Stable',
            'buyer_name' => 'Jan',
            'currency' => 'PLN',
            'subtotal_cents' => $totalCents,
            'vat_cents' => 0,
            'total_cents' => $totalCents,
            'issued_at' => $status !== InvoiceStatus::Draft ? $issuedAt : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeStable(): Tenant
    {
        return Tenant::create([
            'slug' => 'yt-'.Str::random(6),
            'name' => 'Yearly Stable',
            'type' => TenantType::Stable,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
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
            $t->string('central_user_id', 26)->nullable();
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
            $t->string('seller_name');
            $t->string('buyer_name');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
