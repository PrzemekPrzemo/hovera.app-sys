<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Services\Dashboard\TodayDashboardService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TodayDashboardService::trend() — 7-day sparkline data per KPI used
 * by `TodayStatsWidget`. Test pokrywa:
 *   - dla bezstanowej stajni zwraca tablicę zer (7-elementową)
 *   - bookingi z dzisiaj policzone w slocie [6], wczorajsze w [5]
 *   - overdue_care liczy stan historyczny per dzień (next_due_at < day)
 *   - unpaid invoices liczy "as of day X" — paid_at po danym dniu = nadal unpaid wtedy
 *   - cancelled/no-show entries pomijane jak w snapshot()
 */
class TodayDashboardTrendTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_trend_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');
        $this->setUpTenantTables();
        $this->bootTenant();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_returns_seven_zero_slots_for_empty_tenant(): void
    {
        $trend = app(TodayDashboardService::class)->trend(7);

        $this->assertCount(7, $trend['bookings_today']);
        $this->assertCount(7, $trend['overdue_care']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $trend['bookings_today']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $trend['unpaid_invoices_count']);
    }

    public function test_bookings_today_lands_in_last_slot(): void
    {
        $this->seedBooking(now()->copy()->setTime(10, 0));
        $this->seedBooking(now()->copy()->setTime(14, 0));
        $this->seedBooking(now()->copy()->subDay()->setTime(10, 0));

        $trend = app(TodayDashboardService::class)->trend(7);

        $this->assertSame(2, $trend['bookings_today'][6], 'today in slot [6]');
        $this->assertSame(1, $trend['bookings_today'][5], 'yesterday in slot [5]');
        $this->assertSame(0, $trend['bookings_today'][0], '6 days ago in slot [0]');
    }

    public function test_cancelled_bookings_are_excluded(): void
    {
        $this->seedBooking(now()->copy()->setTime(10, 0));
        $this->seedBooking(now()->copy()->setTime(14, 0), status: CalendarEntryStatus::Cancelled);
        $this->seedBooking(now()->copy()->setTime(16, 0), status: CalendarEntryStatus::NoShow);

        $trend = app(TodayDashboardService::class)->trend(7);

        $this->assertSame(1, $trend['bookings_today'][6]);
    }

    public function test_overdue_care_counts_historical_state_per_day(): void
    {
        // Record overdue 5 days ago (next_due_at = 6 days ago).
        DB::connection('tenant')->table('health_records')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => '01HHORSE0000000000000ABC1',
            'type' => 'vaccination',
            'performed_at' => now()->subYear(),
            'summary' => 'Tężec',
            'next_due_at' => now()->subDays(6)->toDateString(),
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ]);

        $trend = app(TodayDashboardService::class)->trend(7);

        // 6 days ago slot [0]: next_due_at = day, not yet < day → 0
        $this->assertSame(0, $trend['overdue_care'][0]);
        // 5 days ago slot [1]: next_due < that day → overdue
        $this->assertSame(1, $trend['overdue_care'][1]);
        // Today slot [6]: still overdue
        $this->assertSame(1, $trend['overdue_care'][6]);
    }

    public function test_unpaid_invoices_count_reflects_as_of_state(): void
    {
        // Issued 3 days ago, paid yesterday. From issuance until payment
        // it should count as unpaid; after payment, it drops.
        DB::connection('tenant')->table('invoices')->insert([
            'id' => (string) Str::ulid(),
            'kind' => 'fv',
            'status' => InvoiceStatus::Issued->value,
            'number' => 'FV/2026/06/T01',
            'seller_name' => 'Stable',
            'buyer_name' => 'X',
            'buyer_type' => 'individual',
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
            'issued_at' => now()->subDays(3)->toDateString(),
            'paid_at' => now()->subDay()->subHour(),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $trend = app(TodayDashboardService::class)->trend(7);

        $this->assertSame(0, $trend['unpaid_invoices_count'][0], '6 days ago: not yet issued');
        $this->assertSame(1, $trend['unpaid_invoices_count'][3], '3 days ago: issued, unpaid');
        $this->assertSame(1, $trend['unpaid_invoices_count'][4], '2 days ago: still unpaid');
        $this->assertSame(0, $trend['unpaid_invoices_count'][6], 'today: already paid');
    }

    private function seedBooking(\DateTimeInterface $start, CalendarEntryStatus $status = CalendarEntryStatus::Confirmed): void
    {
        DB::connection('tenant')->table('calendar_entries')->insert([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+1 hour'),
            'status' => $status->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bootTenant(): void
    {
        $tenant = new Tenant([
            'slug' => 'trend-test',
            'name' => 'Trend Test',
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('calendar_entries', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('type', 32);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('horse_id', 26)->nullable();
            $t->string('instructor_id', 26)->nullable();
            $t->string('arena_id', 26)->nullable();
            $t->string('client_id', 26)->nullable();
            $t->string('status', 32);
            $t->string('title', 160)->nullable();
            $t->text('notes')->nullable();
            $t->integer('price_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('health_records', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('specialist_id', 26)->nullable();
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('summary', 255);
            $t->date('next_due_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('boxes', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('name', 60);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });

        Schema::connection('tenant')->create('invoices', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable()->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26)->nullable();
            $t->string('seller_name');
            $t->string('buyer_name');
            $t->string('buyer_type', 16)->default('individual');
            $t->date('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('currency', 3)->default('PLN');
            $t->integer('subtotal_cents')->default(0);
            $t->integer('vat_cents')->default(0);
            $t->integer('total_cents')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
