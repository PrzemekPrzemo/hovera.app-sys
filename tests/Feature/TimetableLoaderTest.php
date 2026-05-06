<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Services\Calendar\TimetableLoader;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimetableLoaderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_tt_').'.sqlite';
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

    public function test_empty_day_renders_single_lane_when_group_by_none(): void
    {
        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');

        $this->assertCount(1, $result['lanes']);
        $this->assertSame('Wszystkie rezerwacje', $result['lanes'][0]['label']);
        $this->assertCount(0, $result['lanes'][0]['entries']);
    }

    public function test_view_minutes_match_default_06_to_22_window(): void
    {
        $result = $this->loader()->loadDay($this->date());
        $this->assertSame(16 * 60, $result['view_minutes']);
    }

    public function test_entries_outside_view_window_are_excluded(): void
    {
        // 02:00 — way before view start (06:00)
        $this->seedEntry('02:00', '03:00');

        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');
        $this->assertCount(0, $result['lanes'][0]['entries']);
    }

    public function test_entries_overlapping_view_window_are_included_and_clipped(): void
    {
        // 05:00 — 07:00 — starts before view, ends inside.
        $this->seedEntry('05:00', '07:00');

        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');

        $this->assertCount(1, $result['lanes'][0]['entries']);
        $entry = $result['lanes'][0]['entries'][0];
        $this->assertTrue($entry['is_clipped_top']);
        $this->assertSame(0, $entry['top_px']);
        $this->assertSame(60, $entry['height_px']);   // only 06:00-07:00 visible
    }

    public function test_cancelled_entries_are_excluded(): void
    {
        $this->seedEntry('09:00', '10:00', status: CalendarEntryStatus::Cancelled);

        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');
        $this->assertCount(0, $result['lanes'][0]['entries']);
    }

    public function test_type_filter_narrows_results(): void
    {
        $this->seedEntry('09:00', '10:00', type: CalendarEntryType::LessonIndividual);
        $this->seedEntry('10:00', '11:00', type: CalendarEntryType::Care);

        $result = $this->loader()->loadDay(
            $this->date(),
            groupBy: 'none',
            typeFilter: CalendarEntryType::Care->value,
        );

        $this->assertCount(1, $result['lanes'][0]['entries']);
        $this->assertSame(CalendarEntryType::Care, $result['lanes'][0]['entries'][0]['type']);
    }

    public function test_group_by_instructor_creates_lane_per_active_instructor(): void
    {
        DB::connection('tenant')->table('instructors')->insert([
            ['id' => 'INSTR_A', 'name' => 'Anna', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'INSTR_B', 'name' => 'Bartek', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'INSTR_C', 'name' => 'Cezary', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->seedEntry('09:00', '10:00', instructorId: 'INSTR_A');

        $result = $this->loader()->loadDay($this->date(), groupBy: 'instructor');

        $this->assertCount(2, $result['lanes']);   // C is inactive
        $this->assertSame('Anna', $result['lanes'][0]['label']);
        $this->assertSame('Bartek', $result['lanes'][1]['label']);
        $this->assertCount(1, $result['lanes'][0]['entries']);
        $this->assertCount(0, $result['lanes'][1]['entries']);
    }

    public function test_orphan_lane_appears_when_entries_have_no_resource(): void
    {
        DB::connection('tenant')->table('instructors')->insert([
            ['id' => 'INSTR_A', 'name' => 'Anna', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Event without instructor → orphan
        $this->seedEntry('09:00', '10:00', type: CalendarEntryType::Event, instructorId: null);

        $result = $this->loader()->loadDay($this->date(), groupBy: 'instructor');

        // 1 instructor lane + 1 orphan lane
        $this->assertCount(2, $result['lanes']);
        $this->assertSame('Bez przypisania', $result['lanes'][1]['label']);
        $this->assertCount(1, $result['lanes'][1]['entries']);
    }

    public function test_positioning_is_one_pixel_per_minute_from_view_start(): void
    {
        // 09:30 — 10:00 → top = 3.5h * 60 = 210px, height = 30px
        $this->seedEntry('09:30', '10:00');

        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');
        $entry = $result['lanes'][0]['entries'][0];

        $this->assertSame(210, $entry['top_px']);
        $this->assertSame(30, $entry['height_px']);
    }

    public function test_short_entries_get_minimum_height_for_visibility(): void
    {
        // 5-minute entry — should still render as 15min tall
        $this->seedEntry('09:00', '09:05');

        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');
        $this->assertSame(15, $result['lanes'][0]['entries'][0]['height_px']);
    }

    public function test_color_falls_back_to_type_palette_when_no_resource_color(): void
    {
        $this->seedEntry('09:00', '10:00', type: CalendarEntryType::LessonIndividual);

        $result = $this->loader()->loadDay($this->date(), groupBy: 'none');
        $this->assertSame('#10b981', $result['lanes'][0]['entries'][0]['color']);
    }

    private function loader(): TimetableLoader
    {
        return $this->app->make(TimetableLoader::class);
    }

    private function date(): Carbon
    {
        return Carbon::parse('2026-06-15');
    }

    private function seedEntry(
        string $startsAt,
        string $endsAt,
        CalendarEntryType $type = CalendarEntryType::LessonIndividual,
        CalendarEntryStatus $status = CalendarEntryStatus::Confirmed,
        ?string $instructorId = '01HINSTRUCT00000000000001',
    ): void {
        DB::connection('tenant')->table('calendar_entries')->insert([
            'id' => (string) Str::ulid(),
            'type' => $type->value,
            'starts_at' => Carbon::parse('2026-06-15 '.$startsAt.':00'),
            'ends_at' => Carbon::parse('2026-06-15 '.$endsAt.':00'),
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => $instructorId,
            'arena_id' => '01HARENA000000000000000001',
            'status' => $status->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('arenas', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('type', 32)->default('indoor');
            $t->string('color', 7)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

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

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 15)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 32)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('owner_client_id', 26)->nullable();
            $t->string('cover_image_path')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('city', 120)->nullable();
            $t->char('country', 2)->default('PL');
            $t->timestamp('rodo_consent_at')->nullable();
            $t->string('rodo_consent_source', 60)->nullable();
            $t->string('central_user_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
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
            'slug' => 'tt-test',
            'name' => 'TT Test',
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
