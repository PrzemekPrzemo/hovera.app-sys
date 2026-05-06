<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\RecurrencePattern;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Services\Calendar\RecurrenceExpander;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecurrenceExpanderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_recur_').'.sqlite';
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
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_weekly_pattern_emits_dates_on_specified_days(): void
    {
        // Mondays + Wednesdays from 2026-01-05 (Monday) to 2026-01-18
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Weekly->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [1, 3],   // Mon, Wed
            'recurrence_starts_on' => '2026-01-05',
            'recurrence_ends_on' => '2026-01-18',
        ]);

        $dates = $this->expander()->expand($series)
            ->map(fn (array $r) => $r['date']->toDateString())
            ->all();

        // 2026-01-05 (Mon), 01-07 (Wed), 01-12 (Mon), 01-14 (Wed)
        $this->assertSame([
            '2026-01-05', '2026-01-07', '2026-01-12', '2026-01-14',
        ], $dates);
    }

    public function test_weekly_with_interval_2_skips_alternate_weeks(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Weekly->value,
            'recurrence_interval' => 2,
            'recurrence_days_of_week' => [1],
            'recurrence_starts_on' => '2026-01-05',
            'recurrence_ends_on' => '2026-02-08',
        ]);

        $dates = $this->expander()->expand($series)
            ->map(fn (array $r) => $r['date']->toDateString())
            ->all();

        // Every other Monday: 01-05, 01-19, 02-02
        $this->assertSame(['2026-01-05', '2026-01-19', '2026-02-02'], $dates);
    }

    public function test_daily_pattern_emits_consecutive_dates(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Daily->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [],
            'recurrence_starts_on' => '2026-01-10',
            'recurrence_ends_on' => '2026-01-14',
        ]);

        $dates = $this->expander()->expand($series)
            ->map(fn (array $r) => $r['date']->toDateString())
            ->all();

        $this->assertSame(['2026-01-10', '2026-01-11', '2026-01-12', '2026-01-13', '2026-01-14'], $dates);
    }

    public function test_monthly_pattern_preserves_day_of_month(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Monthly->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [],
            'recurrence_starts_on' => '2026-01-15',
            'recurrence_ends_on' => '2026-04-30',
        ]);

        $dates = $this->expander()->expand($series)
            ->map(fn (array $r) => $r['date']->toDateString())
            ->all();

        $this->assertSame(['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'], $dates);
    }

    public function test_max_occurrences_caps_the_series(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Daily->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [],
            'recurrence_starts_on' => '2026-01-01',
            'recurrence_ends_on' => null,
            'max_occurrences' => 3,
        ]);

        $dates = $this->expander()->expand($series, until: Carbon::parse('2026-12-31'));

        $this->assertCount(3, $dates);
    }

    public function test_excludes_dates_already_materialised(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Weekly->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [1],
            'recurrence_starts_on' => '2026-01-05',
            'recurrence_ends_on' => '2026-01-26',
        ]);

        // Pre-create one occurrence (the second Monday) — the expander
        // should skip it on a re-run.
        CalendarEntry::create([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => Carbon::parse('2026-01-12 17:00'),
            'ends_at' => Carbon::parse('2026-01-12 18:00'),
            'recurrence_id' => $series->id,
            'recurrence_occurrence' => 99,
            'status' => CalendarEntryStatus::Confirmed->value,
        ]);

        $dates = $this->expander()->expand($series)
            ->map(fn (array $r) => $r['date']->toDateString())
            ->all();

        // Only the Mondays NOT already present.
        $this->assertSame(['2026-01-05', '2026-01-19', '2026-01-26'], $dates);
    }

    public function test_open_ended_series_capped_at_one_year(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Daily->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [],
            'recurrence_starts_on' => '2026-01-01',
            'recurrence_ends_on' => null,
        ]);

        $dates = $this->expander()->expand($series);

        // Approximately 1 year of days — should be ~366 minus 1 (start
        // through end-of-year). Just ensure it's bounded and reasonable.
        $this->assertGreaterThan(300, $dates->count());
        $this->assertLessThanOrEqual(RecurrenceExpander::MAX_OCCURRENCES_PER_EXPANSION, $dates->count());
    }

    public function test_until_argument_clamps_expansion_horizon(): void
    {
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Daily->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [],
            'recurrence_starts_on' => '2026-01-01',
            'recurrence_ends_on' => '2026-12-31',
        ]);

        $dates = $this->expander()->expand($series, until: Carbon::parse('2026-01-10'));

        $this->assertCount(10, $dates);
    }

    public function test_weekly_without_days_of_week_falls_back_to_start_weekday(): void
    {
        // Wednesday start, no days specified — should produce Wednesdays.
        $series = $this->seedSeries([
            'recurrence_pattern' => RecurrencePattern::Weekly->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [],
            'recurrence_starts_on' => '2026-01-07',  // Wed
            'recurrence_ends_on' => '2026-01-28',
        ]);

        $dates = $this->expander()->expand($series)
            ->map(fn (array $r) => $r['date']->toDateString())
            ->all();

        $this->assertSame(['2026-01-07', '2026-01-14', '2026-01-21', '2026-01-28'], $dates);
    }

    private function expander(): RecurrenceExpander
    {
        return $this->app->make(RecurrenceExpander::class);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function seedSeries(array $overrides = []): RecurringCalendarEntry
    {
        return RecurringCalendarEntry::create(array_merge([
            'name' => 'Test series',
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_time' => '17:00:00',
            'duration_minutes' => 60,
            'recurrence_pattern' => RecurrencePattern::Weekly->value,
            'recurrence_interval' => 1,
            'recurrence_days_of_week' => [1],
            'recurrence_starts_on' => '2026-01-05',
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
    }

    private function fakeActiveTenant(): void
    {
        $tenant = new Tenant([
            'slug' => 'recur-test',
            'name' => 'Recur Test',
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
