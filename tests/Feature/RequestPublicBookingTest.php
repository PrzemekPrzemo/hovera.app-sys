<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\RequestPublicBooking;
use App\Actions\Calendar\UpdateCalendarEntry;
use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Instructor;
use App\Notifications\NewBookingRequestNotification;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class RequestPublicBookingTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_rpb_').'.sqlite';
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
            'id' => '01HINSTRUCT00000000000001',
            'name' => 'Anna',
            'is_active' => true,
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

    public function test_creates_client_and_requested_entry(): void
    {
        Notification::fake();

        $startsAt = Carbon::tomorrow()->setTime(10, 0);

        $result = $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'name' => 'Anna Kowalska',
            'email' => 'ANNA@example.com',
            'phone' => '+48 600 100 200',
            'notes' => 'Pierwsza jazda',
        ]);

        $this->assertInstanceOf(Client::class, $result['client']);
        $this->assertSame('anna@example.com', $result['client']->email);   // case-folded

        $this->assertSame(CalendarEntryStatus::Requested, $result['entry']->status);
        $this->assertNull($result['entry']->horse_id);
        $this->assertSame($this->instructor->id, $result['entry']->instructor_id);
        $this->assertSame($result['client']->id, $result['entry']->client_id);
        $this->assertSame('public_booking', $result['entry']->metadata['source']);
    }

    public function test_disabled_public_booking_throws(): void
    {
        Notification::fake();
        $disabled = $this->makeTenant(enabled: false);

        $this->expectException(ValidationException::class);
        $this->action()->execute($disabled, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'X',
            'email' => 'x@example.com',
        ]);
    }

    public function test_inactive_instructor_throws(): void
    {
        Notification::fake();
        $this->instructor->forceFill(['is_active' => false])->save();

        $this->expectException(ValidationException::class);
        $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'X',
            'email' => 'x@example.com',
        ]);
    }

    public function test_existing_client_is_matched_not_duplicated(): void
    {
        Notification::fake();
        $existing = Client::create([
            'id' => '01HCLIENT00000000000000001',
            'type' => 'individual',
            'name' => 'Anna Stara',
            'email' => 'anna@example.com',
            'country' => 'PL',
        ]);

        $result = $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna Nowa',
            'email' => 'anna@example.com',
        ]);

        $this->assertSame($existing->id, $result['client']->id);
        $this->assertSame(1, Client::count());
    }

    public function test_too_close_booking_rejected(): void
    {
        Notification::fake();

        $this->expectException(ValidationException::class);
        $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => now()->addMinutes(30)->toDateTimeString(),  // < advance_min_hours=4
            'name' => 'X',
            'email' => 'x@example.com',
        ]);
    }

    public function test_owner_cannot_confirm_request_without_assigning_horse(): void
    {
        Notification::fake();

        $result = $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        // Try to flip status to confirmed without setting horse_id → should fail
        $this->expectException(ValidationException::class);
        $this->app->make(UpdateCalendarEntry::class)->execute(
            $result['entry'],
            ['status' => CalendarEntryStatus::Confirmed->value],
        );
    }

    public function test_owner_can_confirm_after_assigning_horse(): void
    {
        Notification::fake();

        $result = $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        $updated = $this->app->make(UpdateCalendarEntry::class)->execute(
            $result['entry'],
            [
                'horse_id' => '01HHORSE0000000000000000A1',
                'status' => CalendarEntryStatus::Confirmed->value,
            ],
        );

        $this->assertSame(CalendarEntryStatus::Confirmed, $updated->status);
        $this->assertSame('01HHORSE0000000000000000A1', $updated->horse_id);
    }

    public function test_notification_dispatched_to_owners(): void
    {
        Notification::fake();

        // Make a master admin who is also the tenant owner so the notification
        // route('mail') call has a recipient.
        $owner = User::create([
            'email' => 'owner@example.com',
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->action()->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        Notification::assertSentOnDemand(NewBookingRequestNotification::class);
    }

    private function action(): RequestPublicBooking
    {
        return $this->app->make(RequestPublicBooking::class);
    }

    private function makeTenant(bool $enabled = true): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'rpb-'.$u,
            'name' => 'RPB Test',
            'db_name' => 'rpb_'.$u,
            'db_username' => 'rpb_'.substr($u, -8),
            'status' => 'active',
            'settings' => [
                'public_booking' => [
                    'enabled' => $enabled,
                    'lesson_duration_minutes' => 60,
                    'working_hours_start' => '09:00',
                    'working_hours_end' => '19:00',
                    'advance_min_hours' => 4,
                    'advance_max_days' => 30,
                ],
            ],
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
}
