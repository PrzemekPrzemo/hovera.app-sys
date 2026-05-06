<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\PassStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Pass;
use App\Models\Tenant\PassUse;
use App\Services\Calendar\PassUseManager;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassUseManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private string $clientId = '01HCLIENT0000000000000001A';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_pass_').'.sqlite';
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
        $this->seedClient();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_apply_consumes_use_from_oldest_expiring_usable_pass(): void
    {
        $longExpiry = $this->seedPass(totalUses: 8, validUntil: now()->addMonth());
        $shortExpiry = $this->seedPass(totalUses: 4, validUntil: now()->addWeek());
        $evergreen = $this->seedPass(totalUses: 10, validUntil: null);

        $entry = $this->seedEntry();

        $use = $this->manager()->applyTo($entry);

        $this->assertNotNull($use);
        $this->assertSame($shortExpiry->id, $use->pass_id);
        $this->assertSame(3, $shortExpiry->refresh()->remaining_uses);
        $this->assertSame(8, $longExpiry->refresh()->remaining_uses);
        $this->assertSame(10, $evergreen->refresh()->remaining_uses);
    }

    public function test_apply_returns_null_when_client_has_no_usable_pass(): void
    {
        $this->seedPass(totalUses: 0);   // already exhausted

        $entry = $this->seedEntry();
        $result = $this->manager()->applyTo($entry);

        $this->assertNull($result);
    }

    public function test_apply_returns_null_when_entry_has_no_client(): void
    {
        $this->seedPass();
        $entry = $this->seedEntry(withClient: false);

        $this->assertNull($this->manager()->applyTo($entry));
    }

    public function test_apply_does_not_double_consume_when_active_use_already_exists(): void
    {
        $pass = $this->seedPass();
        $entry = $this->seedEntry();

        $first = $this->manager()->applyTo($entry);
        $second = $this->manager()->applyTo($entry);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(7, $pass->refresh()->remaining_uses);
    }

    public function test_apply_skips_expired_passes(): void
    {
        $expired = $this->seedPass(validUntil: now()->subDay());
        $expired->forceFill(['status' => PassStatus::Expired->value])->save();

        $valid = $this->seedPass(totalUses: 5, validUntil: now()->addWeek());

        $entry = $this->seedEntry();
        $use = $this->manager()->applyTo($entry);

        $this->assertSame($valid->id, $use->pass_id);
    }

    public function test_restore_brings_use_back_when_within_window(): void
    {
        $pass = $this->seedPass(cancellationHours: 12);
        // Booking 24h in the future — well within a 12h cancellation window
        $entry = $this->seedEntry(startsAt: now()->addDay());

        $this->manager()->applyTo($entry);
        $this->assertSame(7, $pass->refresh()->remaining_uses);

        $restored = $this->manager()->restoreFor($entry);

        $this->assertTrue($restored);
        $this->assertSame(8, $pass->refresh()->remaining_uses);

        $use = PassUse::query()->where('calendar_entry_id', $entry->id)->firstOrFail();
        $this->assertNotNull($use->restored_at);
    }

    public function test_restore_does_not_bring_use_back_when_late(): void
    {
        $pass = $this->seedPass(cancellationHours: 12);
        // Booking in 3h — past the 12h cancel deadline (now + 3h < now + 12h)
        $entry = $this->seedEntry(startsAt: now()->addHours(3));

        $this->manager()->applyTo($entry);
        $this->assertSame(7, $pass->refresh()->remaining_uses);

        $restored = $this->manager()->restoreFor($entry);

        $this->assertFalse($restored);
        $this->assertSame(7, $pass->refresh()->remaining_uses);   // pass NOT restored
    }

    public function test_restore_falls_back_to_tenant_default_when_pass_has_no_override(): void
    {
        // Tenant default = 24h cancellation window
        $tenant = $this->app->make(TenantManager::class)->current();
        $tenant->forceFill(['settings' => ['cancellation_policy' => ['hours' => 24]]])->save();

        $pass = $this->seedPass(cancellationHours: null);
        // Booking 36h ahead — well outside the 24h cancellation deadline.
        $entry = $this->seedEntry(startsAt: now()->addHours(36));

        $this->manager()->applyTo($entry);
        $restored = $this->manager()->restoreFor($entry);

        $this->assertTrue($restored);

        // And the inverse: a booking 12h ahead is INSIDE the 24h
        // cancellation window — too late.
        $latePass = $this->seedPass(cancellationHours: null);
        $lateEntry = $this->seedEntry(startsAt: now()->addHours(12));
        $this->manager()->applyTo($lateEntry);
        $this->assertFalse($this->manager()->restoreFor($lateEntry));
    }

    public function test_recompute_marks_status_exhausted_when_remaining_drops_to_zero(): void
    {
        $pass = $this->seedPass(totalUses: 1);

        $entry = $this->seedEntry();
        $this->manager()->applyTo($entry);

        $this->assertSame(0, $pass->refresh()->remaining_uses);
        $this->assertSame(PassStatus::Exhausted, $pass->refresh()->status);
    }

    public function test_recompute_returns_to_active_when_use_restored(): void
    {
        $pass = $this->seedPass(totalUses: 1, cancellationHours: 12);

        $entry = $this->seedEntry(startsAt: now()->addDay());
        $this->manager()->applyTo($entry);

        $this->assertSame(PassStatus::Exhausted, $pass->refresh()->status);

        $this->manager()->restoreFor($entry);

        $this->assertSame(PassStatus::Active, $pass->refresh()->status);
        $this->assertSame(1, $pass->refresh()->remaining_uses);
    }

    public function test_would_restore_on_cancel_reports_correctly(): void
    {
        $this->seedPass(cancellationHours: 12);

        $earlyEntry = $this->seedEntry(startsAt: now()->addDay());
        $lateEntry = $this->seedEntry(startsAt: now()->addHour());

        $this->manager()->applyTo($earlyEntry);
        $this->manager()->applyTo($lateEntry);

        $this->assertTrue($this->manager()->wouldRestoreOnCancel($earlyEntry));
        $this->assertFalse($this->manager()->wouldRestoreOnCancel($lateEntry));
    }

    public function test_pick_usable_skips_passes_that_havent_started_yet(): void
    {
        $futurePass = $this->seedPass(validFrom: now()->addWeek());
        $currentPass = $this->seedPass(totalUses: 5);

        $picked = $this->manager()->pickUsablePassForClient($this->clientId);

        $this->assertSame($currentPass->id, $picked->id);
    }

    private function manager(): PassUseManager
    {
        return $this->app->make(PassUseManager::class);
    }

    private function seedPass(
        int $totalUses = 8,
        ?Carbon $validFrom = null,
        ?Carbon $validUntil = null,
        ?int $cancellationHours = null,
    ): Pass {
        $pass = Pass::create([
            'client_id' => $this->clientId,
            'name' => 'Karnet '.$totalUses.' jazd',
            'total_uses' => $totalUses,
            'remaining_uses' => $totalUses,
            'valid_from' => $validFrom?->toDateString(),
            'valid_until' => $validUntil?->toDateString(),
            'status' => $totalUses > 0 ? PassStatus::Active->value : PassStatus::Exhausted->value,
            'cancellation_policy_hours' => $cancellationHours,
        ]);

        return $pass;
    }

    private function seedEntry(
        ?Carbon $startsAt = null,
        bool $withClient = true,
    ): CalendarEntry {
        $startsAt ??= now()->addDay();

        return CalendarEntry::create([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'horse_id' => '01HHORSE0000000000000000A1',
            'instructor_id' => '01HINSTRUCT00000000000001',
            'arena_id' => '01HARENA000000000000000001',
            'client_id' => $withClient ? $this->clientId : null,
            'status' => CalendarEntryStatus::Confirmed->value,
        ]);
    }

    private function seedClient(): void
    {
        DB::connection('tenant')->table('clients')->insert([
            'id' => $this->clientId,
            'type' => 'individual',
            'name' => 'Test Client',
            'country' => 'PL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->char('country', 2)->default('PL');
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
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
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
            'slug' => 'pass-test',
            'name' => 'Pass Test',
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
