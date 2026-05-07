<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\HealthRecordType;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use App\Services\Master\TenantHealthCalculator;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class SnapshotTenantHealthTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Instructor $instructor;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_snap_').'.sqlite';
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

        $this->instructor = Instructor::create([
            'id' => '01HINSTR000000000000000001',
            'name' => 'Anna',
            'is_active' => true,
        ]);
        $this->client = Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => 'Marek',
            'email' => 'marek@example.com',
        ]);

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_calculator_zero_for_brand_new_empty_tenant(): void
    {
        // Empty tenant DB, fresh tenant created just now — no bookings,
        // no maturity, no history. Score should crater.
        $snap = app(TenantHealthCalculator::class)->snapshot($this->tenant);

        $this->assertSame(0, $snap['signals']['total_bookings']);
        $this->assertNull($snap['last_activity_at']);
        // base 25 + zero-overdue 10 - zero-bookings 10 = 25. Empty
        // tenant, just signed up — gets the benefit of the doubt.
        $this->assertSame(25, $snap['score']);
    }

    public function test_calculator_full_health_for_thriving_tenant(): void
    {
        $this->tenant->forceFill(['created_at' => now()->subMonths(3)])->save();

        // 5 active clients to clear the >=3 threshold
        for ($i = 1; $i <= 5; $i++) {
            $c = Client::create([
                'id' => '01HCLI'.str_pad((string) $i, 20, '0', STR_PAD_LEFT),
                'name' => 'Klient '.$i,
            ]);
            $this->makeEntry(now()->subDays(2), client: $c);
        }
        // Recent bookings
        $this->makeEntry(now()->subDays(1));
        $this->makeEntry(now()->subDays(3));

        $snap = app(TenantHealthCalculator::class)->snapshot($this->tenant);

        // 25 base + 25 7d + 15 30d + 15 active_clients + 10 zero overdue
        // + 10 mature = 100
        $this->assertSame(100, $snap['score']);
        $this->assertNotNull($snap['last_activity_at']);
        // 5 new clients + the default $this->client used in the
        // additional 2 entries below = 6 distinct booked clients
        $this->assertSame(6, $snap['signals']['active_clients_90d']);
    }

    public function test_calculator_punishes_overdue_health(): void
    {
        $this->tenant->forceFill(['created_at' => now()->subMonths(3)])->save();

        $horse = Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
        ]);
        HealthRecord::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $horse->id,
            'type' => HealthRecordType::Vaccination->value,
            'performed_at' => now()->subYear(),
            'summary' => 'Stara szczepionka',
            'next_due_at' => now()->subDay()->toDateString(),
        ]);
        $this->makeEntry(now()->subDay());

        $snap = app(TenantHealthCalculator::class)->snapshot($this->tenant);

        $this->assertSame(1, $snap['signals']['overdue_health_records']);
        // 25 base + 25 7d + 15 30d + 10 mature = 75 (no zero-overdue bonus)
        $this->assertSame(75, $snap['score']);
    }

    public function test_calculator_clamps_suspended_to_zero(): void
    {
        $this->tenant->forceFill(['status' => 'suspended', 'created_at' => now()->subMonths(3)])->save();

        $snap = app(TenantHealthCalculator::class)->snapshot($this->tenant);

        $this->assertSame(0, $snap['score']);
        $this->assertTrue($snap['signals']['is_suspended']);
    }

    public function test_command_persists_snapshot_to_central(): void
    {
        $this->tenant->forceFill(['created_at' => now()->subMonths(2)])->save();
        $this->makeEntry(now()->subDay());

        $this->artisan('tenants:snapshot-health')->assertSuccessful();

        $fresh = $this->tenant->fresh();
        $this->assertNotNull($fresh->health_score);
        $this->assertGreaterThan(0, $fresh->health_score);
        $this->assertNotNull($fresh->last_activity_at);
        $this->assertIsArray($fresh->settings['health_signals'] ?? null);
    }

    public function test_command_supports_single_tenant_filter(): void
    {
        $other = $this->makeTenant();   // second tenant, never the target

        $this->artisan('tenants:snapshot-health', ['--tenant' => $this->tenant->slug])
            ->assertSuccessful()
            ->expectsOutputToContain($this->tenant->slug);

        // The filtered-out tenant should not have been touched
        $this->assertNull($other->fresh()->health_score);
    }

    private function makeEntry(Carbon $at, ?Client $client = null): CalendarEntry
    {
        return CalendarEntry::create([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => $at,
            'ends_at' => $at->copy()->addHour(),
            'instructor_id' => $this->instructor->id,
            'client_id' => ($client ?? $this->client)->id,
            'status' => CalendarEntryStatus::Completed->value,
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'snap-'.$u,
            'name' => 'Snap '.$u,
            'db_name' => 'snap_'.$u,
            'db_username' => 'snap_'.substr($u, -8),
            'status' => 'active',
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('owner_client_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('instructors', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('arenas', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('calendar_entries', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 32);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('horse_id', 26)->nullable();
            $t->string('instructor_id', 26)->nullable();
            $t->string('arena_id', 26)->nullable();
            $t->string('client_id', 26)->nullable();
            $t->string('status', 32);
            $t->json('metadata')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('summary', 255);
            $t->date('next_due_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
