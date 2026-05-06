<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\CreateRecurringSeries;
use App\Actions\Calendar\DeleteRecurringSeries;
use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\RecurrencePattern;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateRecurringSeriesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_crs_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->fakeActiveTenant();

        // Stub the audit logger — its tenant table isn't part of the
        // minimal schema in this test harness.
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_creates_calendar_entries_for_each_occurrence(): void
    {
        $series = $this->seedSeries();

        $result = $this->app->make(CreateRecurringSeries::class)->execute($series);

        $this->assertSame(4, $result['created']);   // 4 Mondays Jan 5-26
        $this->assertCount(0, $result['skipped_conflicts']);

        $entries = CalendarEntry::query()
            ->where('recurrence_id', $series->id)
            ->orderBy('starts_at')
            ->get();

        $this->assertCount(4, $entries);
        $this->assertSame('17:00', $entries[0]->starts_at->format('H:i'));
        $this->assertSame('18:00', $entries[0]->ends_at->format('H:i'));
        $this->assertSame(CalendarEntryStatus::Confirmed, $entries[0]->status);
        $this->assertSame(1, $entries[0]->recurrence_occurrence);
        $this->assertSame(4, $entries[3]->recurrence_occurrence);
    }

    public function test_skips_dates_that_would_conflict(): void
    {
        $series = $this->seedSeries();

        // Pre-block 2026-01-12 17:00 with a different recurrence id to
        // simulate a manual booking that owns the slot.
        DB::connection('tenant')->table('calendar_entries')->insert([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-01-12 17:30:00',
            'ends_at' => '2026-01-12 18:30:00',
            'horse_id' => $series->horse_id,
            'instructor_id' => $series->instructor_id,
            'arena_id' => $series->arena_id,
            'status' => CalendarEntryStatus::Confirmed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->app->make(CreateRecurringSeries::class)->execute($series);

        $this->assertSame(3, $result['created']);
        $this->assertSame(['2026-01-12'], $result['skipped_conflicts']);
    }

    public function test_calling_again_does_not_double_create(): void
    {
        $series = $this->seedSeries();

        $first = $this->app->make(CreateRecurringSeries::class)->execute($series);
        $second = $this->app->make(CreateRecurringSeries::class)->execute($series);

        $this->assertSame(4, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(4, CalendarEntry::query()->where('recurrence_id', $series->id)->count());
    }

    public function test_delete_series_cancels_future_keeps_past(): void
    {
        $series = $this->seedSeries([
            'recurrence_starts_on' => Carbon::now()->subWeeks(2)->startOfWeek(Carbon::SUNDAY)->addDays(1)->toDateString(),
            'recurrence_ends_on' => Carbon::now()->addWeeks(2)->startOfWeek(Carbon::SUNDAY)->addDays(1)->toDateString(),
        ]);

        $this->app->make(CreateRecurringSeries::class)->execute($series);

        $totalBefore = CalendarEntry::query()->where('recurrence_id', $series->id)->count();
        $this->assertGreaterThanOrEqual(2, $totalBefore);

        $futureBefore = CalendarEntry::query()
            ->where('recurrence_id', $series->id)
            ->where('starts_at', '>=', now())
            ->count();

        $result = $this->app->make(DeleteRecurringSeries::class)->execute($series);

        $this->assertSame($futureBefore, $result['cancelled']);

        // Past entries still alive (not soft-deleted)
        $pastSurviving = CalendarEntry::query()
            ->where('recurrence_id', $series->id)
            ->where('starts_at', '<', now())
            ->count();
        $this->assertGreaterThan(0, $pastSurviving);

        // Future entries soft-deleted
        $futureRemaining = CalendarEntry::query()
            ->where('recurrence_id', $series->id)
            ->where('starts_at', '>=', now())
            ->count();
        $this->assertSame(0, $futureRemaining);

        // Master soft-deleted
        $this->assertNotNull($series->fresh()->deleted_at);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function seedSeries(array $overrides = []): RecurringCalendarEntry
    {
        return RecurringCalendarEntry::create(array_merge([
            'name' => 'Szkółka pon. 17:00',
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_time' => '17:00:00',
            'duration_minutes' => 60,
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
            'recurrence_pattern' => RecurrencePattern::Weekly->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [1],
            'recurrence_starts_on' => '2026-01-05',
            'recurrence_ends_on' => '2026-01-26',
            'is_active' => true,
        ], $overrides));
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('recurring_calendar_entries', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 160);
            $t->string('type', 32);
            $t->time('starts_time');
            $t->unsignedSmallInteger('duration_minutes');
            $t->string('horse_id', 26)->nullable();
            $t->string('instructor_id', 26)->nullable();
            $t->string('arena_id', 26)->nullable();
            $t->string('client_id', 26)->nullable();
            $t->string('recurrence_pattern', 16);
            $t->unsignedTinyInteger('recurrence_interval')->default(1);
            $t->json('recurrence_days_of_week')->nullable();
            $t->date('recurrence_starts_on');
            $t->date('recurrence_ends_on')->nullable();
            $t->unsignedSmallInteger('max_occurrences')->nullable();
            $t->string('title', 160)->nullable();
            $t->text('notes')->nullable();
            $t->unsignedInteger('price_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('created_by_central_user_id', 26)->nullable();
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
            $t->string('recurrence_id', 26)->nullable();
            $t->unsignedSmallInteger('recurrence_occurrence')->nullable();
            $t->string('status', 32);
            $t->string('title', 160)->nullable();
            $t->text('notes')->nullable();
            $t->integer('price_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('passes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('client_id', 26);
            $t->string('name', 120);
            $t->unsignedSmallInteger('total_uses');
            $t->smallInteger('remaining_uses');
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->unsignedInteger('price_cents')->nullable();
            $t->string('status', 32)->default('active');
            $t->unsignedSmallInteger('cancellation_policy_hours')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('pass_uses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('pass_id', 26);
            $t->string('calendar_entry_id', 26);
            $t->timestamp('consumed_at');
            $t->timestamp('restored_at')->nullable();
            $t->string('restored_reason', 120)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }

    private function fakeActiveTenant(): void
    {
        $tenant = new Tenant([
            'slug' => 'crs-test',
            'name' => 'CRS Test',
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }
}
