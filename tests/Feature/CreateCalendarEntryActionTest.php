<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\CalendarConflictException;
use App\Actions\Calendar\CreateCalendarEntry;
use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateCalendarEntryActionTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_create_').'.sqlite';
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

        // Stub the audit logger so tests don't need a working `audit_log` table.
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_creates_lesson_individual_with_all_resources(): void
    {
        $entry = $this->action()->execute([
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-06-15 09:00',
            'ends_at' => '2026-06-15 10:00',
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
            'client_id' => '01HCLIENT00000000000000001',
        ]);

        $this->assertSame(CalendarEntryType::LessonIndividual, $entry->type);
        $this->assertSame(CalendarEntryStatus::Confirmed, $entry->status);
        $this->assertSame(60, $entry->durationMinutes());
    }

    public function test_lesson_individual_requires_horse(): void
    {
        $this->expectException(ValidationException::class);

        $this->action()->execute([
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-06-15 09:00',
            'ends_at' => '2026-06-15 10:00',
            'instructor_id' => '01HINSTRUCT00000000000001',
        ]);
    }

    public function test_lesson_individual_requires_instructor(): void
    {
        $this->expectException(ValidationException::class);

        $this->action()->execute([
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-06-15 09:00',
            'ends_at' => '2026-06-15 10:00',
            'horse_id' => '01HHORSE0000000000000000A1',
        ]);
    }

    public function test_event_does_not_require_horse_or_instructor(): void
    {
        $entry = $this->action()->execute([
            'type' => CalendarEntryType::Event->value,
            'starts_at' => '2026-06-15 09:00',
            'ends_at' => '2026-06-15 18:00',
            'title' => 'Zawody Halowe',
        ]);

        $this->assertSame(CalendarEntryType::Event, $entry->type);
        $this->assertSame('Zawody Halowe', $entry->title);
    }

    public function test_block_requires_arena_or_horse(): void
    {
        $this->expectException(ValidationException::class);

        $this->action()->execute([
            'type' => CalendarEntryType::Block->value,
            'starts_at' => '2026-06-15 09:00',
            'ends_at' => '2026-06-15 10:00',
            'title' => 'Konserwacja',
        ]);
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $this->expectException(ValidationException::class);

        $this->action()->execute([
            'type' => CalendarEntryType::Event->value,
            'starts_at' => '2026-06-15 10:00',
            'ends_at' => '2026-06-15 10:00',
            'title' => 'Same time',
        ]);
    }

    public function test_conflict_with_existing_horse_booking_throws(): void
    {
        $this->seedEntry('09:00', '10:00');

        $this->expectException(CalendarConflictException::class);

        $this->action()->execute([
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-06-15 09:30',
            'ends_at' => '2026-06-15 10:30',
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000099',  // different
            'arena_id' => '01HARENA000000000000000099',      // different
        ]);
    }

    public function test_back_to_back_bookings_succeed(): void
    {
        $this->seedEntry('09:00', '10:00');

        $entry = $this->action()->execute([
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-06-15 10:00',
            'ends_at' => '2026-06-15 11:00',
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
        ]);

        $this->assertNotNull($entry->id);
    }

    public function test_cancelled_existing_entries_dont_block_new_ones(): void
    {
        $this->seedEntry('09:00', '10:00', status: CalendarEntryStatus::Cancelled);

        $entry = $this->action()->execute([
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => '2026-06-15 09:00',
            'ends_at' => '2026-06-15 10:00',
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
        ]);

        $this->assertNotNull($entry->id);
    }

    private function action(): CreateCalendarEntry
    {
        return $this->app->make(CreateCalendarEntry::class);
    }

    private function seedEntry(
        string $startsAt,
        string $endsAt,
        CalendarEntryStatus $status = CalendarEntryStatus::Confirmed,
    ): void {
        DB::connection('tenant')->table('calendar_entries')->insert([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => Carbon::parse('2026-06-15 '.$startsAt.':00'),
            'ends_at' => Carbon::parse('2026-06-15 '.$endsAt.':00'),
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
            'status' => $status->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        $tenant = new Tenant([
            'slug' => 'create-test',
            'name' => 'Create Test',
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
