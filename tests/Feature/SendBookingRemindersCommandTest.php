<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Instructor;
use App\Notifications\BookingReminderClientNotification;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class SendBookingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Instructor $instructor;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_rem_').'.sqlite';
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
            'name' => 'Anna Kowal',
            'is_active' => true,
        ]);
        $this->client = Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => 'Marek Klient',
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

    public function test_sends_reminder_for_booking_in_24h_window(): void
    {
        Notification::fake();

        $entry = $this->makeEntry(now()->addHours(24)->addMinutes(15));

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertSentOnDemand(BookingReminderClientNotification::class);

        $this->assertNotNull($entry->fresh()->reminder_sent_at);
    }

    public function test_does_not_send_reminder_for_booking_outside_window(): void
    {
        Notification::fake();

        $this->makeEntry(now()->addDays(5));   // far future
        $this->makeEntry(now()->addHours(2));  // too soon

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_skips_already_reminded_entries(): void
    {
        Notification::fake();

        $entry = $this->makeEntry(now()->addHours(24));
        $entry->forceFill(['reminder_sent_at' => now()->subHour()])->save();

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_only_confirmed_status_triggers_reminder(): void
    {
        Notification::fake();

        $this->makeEntry(now()->addHours(24), status: CalendarEntryStatus::Requested);
        $this->makeEntry(now()->addHours(24), status: CalendarEntryStatus::Cancelled);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_skips_clients_without_email_but_marks_sent(): void
    {
        Notification::fake();

        $silent = Client::create([
            'id' => '01HCLI0000000000000000999',
            'name' => 'Bez emaila',
        ]);

        $entry = $this->makeEntry(now()->addHours(24), clientId: $silent->id);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        // Marked sent so we don't keep retrying every hour for an undeliverable.
        $this->assertNotNull($entry->fresh()->reminder_sent_at);
    }

    public function test_double_run_does_not_double_send(): void
    {
        Notification::fake();

        $this->makeEntry(now()->addHours(24));

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertSentOnDemandTimes(BookingReminderClientNotification::class, 1);
    }

    public function test_inactive_tenant_is_skipped(): void
    {
        Notification::fake();

        $this->tenant->forceFill(['status' => 'suspended'])->save();
        $this->makeEntry(now()->addHours(24));

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    private function makeEntry(
        Carbon $startsAt,
        CalendarEntryStatus $status = CalendarEntryStatus::Confirmed,
        ?string $clientId = null,
    ): CalendarEntry {
        return CalendarEntry::create([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::LessonIndividual->value,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'instructor_id' => $this->instructor->id,
            'client_id' => $clientId ?? $this->client->id,
            'status' => $status->value,
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'rem-'.$u,
            'name' => 'Reminder Test',
            'db_name' => 'rem_'.$u,
            'db_username' => 'rem_'.substr($u, -8),
            'status' => 'active',
            'settings' => [
                'public_profile' => [
                    'address' => 'ul. Kasztanowa 7',
                    'phone' => '+48 600 000 000',
                ],
                'cancellation_policy' => ['hours' => 24],
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

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
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

        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
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
            $t->timestamp('reminder_sent_at')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
