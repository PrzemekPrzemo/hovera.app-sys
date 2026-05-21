<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Filament\App\Resources\CalendarEntryResource;
use App\Models\Tenant\CalendarEntry;
use App\Services\Tenancy\TenantRoleGate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Pokrywa row-level authorization na CalendarEntryResource (G3 z audytu
 * ról). Instruktor A nie może edytować/skasować lekcji instruktora B —
 * tylko owner/admin/manager mają pełny override.
 */
class CalendarEntryRowLevelAuthTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimalna in-memory SQLite żeby Eloquent Model::save() działało
        // (CalendarEntry potrzebuje connection 'tenant').
        $this->dbPath = tempnam(sys_get_temp_dir(), 'hov_caltest_').'.sqlite';
        touch($this->dbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->dbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('calendar_entries', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 32);
            $t->string('status', 32);
            $t->string('title')->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('horse_id', 26)->nullable();
            $t->string('instructor_id', 26)->nullable();
            $t->string('arena_id', 26)->nullable();
            $t->string('client_id', 26)->nullable();
            $t->string('recurrence_id', 26)->nullable();
            $t->integer('recurrence_occurrence')->nullable();
            $t->text('notes')->nullable();
            $t->integer('price_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        parent::tearDown();
    }

    public function test_manager_can_modify_any_entry(): void
    {
        $entry = $this->makeEntry(createdBy: '01HUSER000000000000000ALICE');
        $this->bindGate(role: 'manager', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertTrue(CalendarEntryResource::canModify($entry));
    }

    public function test_admin_can_modify_other_users_entry(): void
    {
        $entry = $this->makeEntry(createdBy: '01HUSER000000000000000ALICE');
        $this->bindGate(role: 'admin', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertTrue(CalendarEntryResource::canModify($entry));
    }

    public function test_instructor_can_modify_own_entry(): void
    {
        $entry = $this->makeEntry(createdBy: '01HUSER000000000000000ALICE');
        $this->bindGate(role: 'instructor', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER000000000000000ALICE');

        $this->assertTrue(CalendarEntryResource::canModify($entry));
    }

    public function test_instructor_cannot_modify_peers_entry(): void
    {
        $entry = $this->makeEntry(createdBy: '01HUSER000000000000000ALICE');
        $this->bindGate(role: 'instructor', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertFalse(CalendarEntryResource::canModify($entry));
    }

    public function test_vet_cannot_modify_instructor_entry(): void
    {
        $entry = $this->makeEntry(createdBy: '01HUSER000000000000000ALICE');
        $this->bindGate(role: 'vet', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertFalse(CalendarEntryResource::canModify($entry));
    }

    public function test_legacy_entry_without_creator_blocked_for_non_managers(): void
    {
        $entry = $this->makeEntry(createdBy: null);
        $this->bindGate(role: 'instructor', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertFalse(CalendarEntryResource::canModify($entry));
    }

    public function test_legacy_entry_without_creator_still_modifiable_by_manager(): void
    {
        $entry = $this->makeEntry(createdBy: null);
        $this->bindGate(role: 'manager', isMasterAdmin: false);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertTrue(CalendarEntryResource::canModify($entry));
    }

    public function test_master_admin_always_passes(): void
    {
        $entry = $this->makeEntry(createdBy: '01HUSER000000000000000ALICE');
        $this->bindGate(role: 'employee', isMasterAdmin: true);
        Auth::shouldReceive('id')->andReturn('01HUSER0000000000000000BOB');

        $this->assertTrue(CalendarEntryResource::canModify($entry));
    }

    private function makeEntry(?string $createdBy): CalendarEntry
    {
        $entry = new CalendarEntry;
        $entry->forceFill([
            'id' => '01HENTRY00000000000000'.bin2hex(random_bytes(2)),
            'type' => CalendarEntryType::LessonIndividual->value,
            'status' => CalendarEntryStatus::Confirmed->value,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'created_by_central_user_id' => $createdBy,
        ])->save();

        return $entry->refresh();
    }

    private function bindGate(string $role, bool $isMasterAdmin): void
    {
        $mock = Mockery::mock(TenantRoleGate::class);
        $mock->shouldReceive('isMasterAdmin')->andReturn($isMasterAdmin);
        $mock->shouldReceive('isAnyOf')->andReturnUsing(
            fn (array $roles) => in_array($role, $roles, true),
        );

        $this->app->instance(TenantRoleGate::class, $mock);
    }
}
