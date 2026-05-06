<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Services\Calendar\ConflictDetector;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for ConflictDetector. We point the `tenant` connection at
 * a temp-file SQLite (per-test fresh) so we can exercise the model
 * scopes without touching real MySQL.
 */
class CalendarConflictDetectorTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_cal_').'.sqlite';
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

    public function test_no_conflict_for_disjoint_intervals(): void
    {
        $horseId = $this->seedEntry('09:00', '10:00')['horse_id'];

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($horseId, $this->t('11:00'), $this->t('12:00'));

        $this->assertCount(0, $conflicts);
    }

    public function test_overlap_detected_when_intervals_touch_in_middle(): void
    {
        $row = $this->seedEntry('09:00', '10:30');

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($row['horse_id'], $this->t('10:00'), $this->t('11:00'));

        $this->assertCount(1, $conflicts);
    }

    public function test_back_to_back_intervals_do_not_conflict(): void
    {
        // 09:00-10:00 and 10:00-11:00 should NOT clash (half-open intervals).
        $row = $this->seedEntry('09:00', '10:00');

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($row['horse_id'], $this->t('10:00'), $this->t('11:00'));

        $this->assertCount(0, $conflicts);
    }

    public function test_cancelled_entries_dont_block(): void
    {
        $row = $this->seedEntry('09:00', '10:00', status: CalendarEntryStatus::Cancelled);

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($row['horse_id'], $this->t('09:00'), $this->t('10:00'));

        $this->assertCount(0, $conflicts);
    }

    public function test_no_show_entries_dont_block(): void
    {
        $row = $this->seedEntry('09:00', '10:00', status: CalendarEntryStatus::NoShow);

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($row['horse_id'], $this->t('09:00'), $this->t('10:00'));

        $this->assertCount(0, $conflicts);
    }

    public function test_completed_entries_still_block(): void
    {
        $row = $this->seedEntry('09:00', '10:00', status: CalendarEntryStatus::Completed);

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($row['horse_id'], $this->t('09:30'), $this->t('10:30'));

        $this->assertCount(1, $conflicts);
    }

    public function test_separate_resources_dont_clash(): void
    {
        // Different horse → no conflict
        $this->seedEntry('09:00', '10:00');

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse('01HOTHER000000000000000000', $this->t('09:00'), $this->t('10:00'));

        $this->assertCount(0, $conflicts);
    }

    public function test_ignore_entry_id_excludes_self(): void
    {
        $row = $this->seedEntry('09:00', '10:00');

        $conflicts = $this->app->make(ConflictDetector::class)
            ->forHorse($row['horse_id'], $this->t('09:00'), $this->t('10:00'), ignoreEntryId: $row['id']);

        $this->assertCount(0, $conflicts);
    }

    public function test_for_proposed_entry_runs_all_three_checks(): void
    {
        $row = $this->seedEntry('09:00', '10:00');

        $conflicts = $this->app->make(ConflictDetector::class)->forProposedEntry(
            horseId: $row['horse_id'],
            instructorId: $row['instructor_id'],
            arenaId: $row['arena_id'],
            startsAt: $this->t('09:30'),
            endsAt: $this->t('10:30'),
        );

        $this->assertTrue($this->app->make(ConflictDetector::class)->hasAnyConflict($conflicts));
        $this->assertCount(1, $conflicts['horse']);
        $this->assertCount(1, $conflicts['instructor']);
        $this->assertCount(1, $conflicts['arena']);
    }

    private function setUpTenantTables(): void
    {
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
        // The detector queries the `tenant` connection directly via the
        // CalendarEntry model. We don't strictly need TenantManager set
        // up because the model uses the connection name; but other code
        // paths might check hasTenant(). Just make it look real enough.
        $tenant = new Tenant([
            'slug' => 'test-stable',
            'name' => 'Test Stable',
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

    private function seedEntry(
        string $startsAt,
        string $endsAt,
        CalendarEntryStatus $status = CalendarEntryStatus::Confirmed,
    ): array {
        $row = [
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => $this->t($startsAt),
            'ends_at' => $this->t($endsAt),
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
            'client_id' => null,
            'status' => $status->value,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('tenant')->table('calendar_entries')->insert($row);

        return $row;
    }

    private function t(string $hhmm): Carbon
    {
        return Carbon::parse('2026-06-15 '.$hhmm.':00');
    }
}
