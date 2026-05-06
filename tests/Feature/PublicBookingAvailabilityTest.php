<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Instructor;
use App\Services\Calendar\PublicBookingAvailability;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicBookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_pba_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->tenant = $this->makeTenant([
            'enabled' => true,
            'lesson_duration_minutes' => 60,
            'working_hours_start' => '09:00',
            'working_hours_end' => '12:00',
            'advance_min_hours' => 0,
            'advance_max_days' => 30,
        ]);
        $this->instructor = Instructor::create([
            'id' => '01HINSTRUCT00000000000001',
            'name' => 'Anna',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_enabled_false_returns_empty(): void
    {
        $tenant = $this->makeTenant(['enabled' => false]);
        $slots = $this->avail()->slotsFor($tenant, $this->instructor, Carbon::tomorrow());
        $this->assertCount(0, $slots);
    }

    public function test_returns_slots_within_working_hours(): void
    {
        $slots = $this->avail()
            ->slotsFor($this->tenant, $this->instructor, Carbon::tomorrow())
            ->map(fn (Carbon $s) => $s->format('H:i'))
            ->all();

        $this->assertSame(['09:00', '10:00', '11:00'], $slots);
    }

    public function test_excludes_busy_slots_from_existing_entries(): void
    {
        $this->seedBlockingEntry(Carbon::tomorrow()->setTime(10, 0), 60);

        $slots = $this->avail()
            ->slotsFor($this->tenant, $this->instructor, Carbon::tomorrow())
            ->map(fn (Carbon $s) => $s->format('H:i'))
            ->all();

        $this->assertSame(['09:00', '11:00'], $slots);
    }

    public function test_advance_min_hours_skips_too_close_slots(): void
    {
        // Freeze time so we can reason about advance windows precisely.
        // 07:55 + 3h = 10:55 cutoff → 09:00 and 10:00 too close, 11:00 OK.
        $this->travelTo(Carbon::tomorrow()->setTime(7, 55));

        $tenant = $this->makeTenant([
            'enabled' => true,
            'lesson_duration_minutes' => 60,
            'working_hours_start' => '09:00',
            'working_hours_end' => '12:00',
            'advance_min_hours' => 3,
            'advance_max_days' => 30,
        ]);

        $slots = $this->avail()
            ->slotsFor($tenant, $this->instructor, Carbon::today())
            ->map(fn (Carbon $s) => $s->format('H:i'))
            ->all();

        $this->assertSame(['11:00'], $slots);
    }

    public function test_advance_max_days_returns_no_slots_for_far_future(): void
    {
        $tenant = $this->makeTenant([
            'enabled' => true,
            'lesson_duration_minutes' => 60,
            'working_hours_start' => '09:00',
            'working_hours_end' => '12:00',
            'advance_min_hours' => 0,
            'advance_max_days' => 7,
        ]);

        $slots = $this->avail()->slotsFor($tenant, $this->instructor, Carbon::today()->addMonth());
        $this->assertCount(0, $slots);
    }

    public function test_dates_with_slots_only_lists_days_that_have_availability(): void
    {
        $tenant = $this->makeTenant([
            'enabled' => true,
            'lesson_duration_minutes' => 60,
            'working_hours_start' => '09:00',
            'working_hours_end' => '11:00',
            'advance_min_hours' => 0,
            'advance_max_days' => 3,
        ]);

        // Block the entire instructor's window for tomorrow → 0 slots that day
        $this->seedBlockingEntry(Carbon::tomorrow()->setTime(9, 0), 120);

        $dates = $this->avail()->datesWithSlots($tenant, $this->instructor);

        $this->assertNotContains(Carbon::tomorrow()->toDateString(), $dates);
        $this->assertContains(Carbon::today()->addDays(2)->toDateString(), $dates);
    }

    private function avail(): PublicBookingAvailability
    {
        return $this->app->make(PublicBookingAvailability::class);
    }

    private function makeTenant(array $publicBooking): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'pba-'.$u,
            'name' => 'PBA Test',
            'db_name' => 'irrelevant_'.$u,
            'db_username' => 'irrelevant_'.substr($u, -8),
            'status' => 'active',
            'settings' => ['public_booking' => $publicBooking],
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

    private function seedBlockingEntry(Carbon $startsAt, int $minutes): void
    {
        DB::connection('tenant')->table('calendar_entries')->insert([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($minutes),
            'instructor_id' => $this->instructor->id,
            'status' => CalendarEntryStatus::Confirmed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('instructors', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_user_id', 26)->nullable();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->unsignedInteger('hourly_rate_cents')->nullable();
            $t->string('color', 7)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
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
}
