<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\PassStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Instructor;
use App\Models\Tenant\Pass;
use App\Models\Tenant\PassUse;
use App\Notifications\BookingRescheduledClientNotification;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Iter. 17b — covers karnety widget on the dashboard and the
 * client-driven reschedule flow.
 */
class ClientPortalV2Test extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    private Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_pv2_').'.sqlite';
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

        $this->client = Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => 'Marek Klient',
            'email' => 'marek@example.com',
        ]);
        $this->instructor = Instructor::create([
            'id' => '01HINSTR000000000000000001',
            'name' => 'Anna',
            'is_active' => true,
        ]);

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        $this->loginAs($this->client);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_dashboard_renders_active_passes_with_progress(): void
    {
        Pass::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'name' => 'Karnet 10x',
            'total_uses' => 10,
            'remaining_uses' => 4,
            'valid_until' => now()->addMonths(3)->toDateString(),
            'status' => PassStatus::Active->value,
        ]);

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertSee('Karnet 10x')
            ->assertSee('4 / 10 pozostało');
    }

    public function test_dashboard_omits_other_clients_passes(): void
    {
        $other = Client::create([
            'id' => '01HCLI0000000000000000777',
            'name' => 'Inny',
            'email' => 'i@example.com',
        ]);
        Pass::create([
            'id' => (string) Str::ulid(),
            'client_id' => $other->id,
            'name' => 'Cudzy karnet',
            'total_uses' => 5,
            'remaining_uses' => 5,
            'status' => PassStatus::Active->value,
        ]);

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $this->assertStringNotContainsString('Cudzy karnet', (string) $response->getContent());
    }

    public function test_dashboard_shows_recent_pass_uses(): void
    {
        $pass = Pass::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'name' => 'K10',
            'total_uses' => 10,
            'remaining_uses' => 9,
            'status' => PassStatus::Active->value,
        ]);
        $entry = $this->makeEntry(now()->subDays(3), CalendarEntryStatus::Completed);
        PassUse::create([
            'id' => (string) Str::ulid(),
            'pass_id' => $pass->id,
            'calendar_entry_id' => $entry->id,
            'consumed_at' => now()->subDays(3),
        ]);

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $response->assertOk()->assertSee('Ostatnio użyte');
    }

    public function test_reschedule_show_lists_dates_with_slots(): void
    {
        $entry = $this->makeEntry(now()->addDays(3)->setTime(10, 0), CalendarEntryStatus::Confirmed);

        $response = $this->get(route('client_portal.reschedule.show', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]));

        $response->assertOk()->assertSee('Przesuń rezerwację');
    }

    public function test_reschedule_show_404_for_other_clients_entry(): void
    {
        $other = Client::create([
            'id' => '01HCLI0000000000000000888',
            'name' => 'Inny',
        ]);
        $entry = $this->makeEntry(now()->addDays(3)->setTime(10, 0), CalendarEntryStatus::Confirmed, clientId: $other->id);

        $this->get(route('client_portal.reschedule.show', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]))->assertNotFound();
    }

    public function test_reschedule_submit_moves_booking_and_sends_mail(): void
    {
        Notification::fake();

        $entry = $this->makeEntry(now()->addDays(3)->setTime(10, 0), CalendarEntryStatus::Confirmed);
        $newSlot = $this->firstAvailableSlotInWindow();

        $this->post(route('client_portal.reschedule.submit', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]), [
            'starts_at' => $newSlot->toDateTimeString(),
        ])->assertRedirect(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $entry->refresh();
        $this->assertTrue($entry->starts_at->equalTo($newSlot));
        $this->assertSame(1, (int) data_get($entry->metadata, 'client_reschedule_count'));

        Notification::assertSentOnDemand(BookingRescheduledClientNotification::class);
    }

    public function test_reschedule_blocks_after_reaching_limit(): void
    {
        $entry = $this->makeEntry(now()->addDays(3)->setTime(10, 0), CalendarEntryStatus::Confirmed);
        $entry->forceFill([
            'metadata' => ['client_reschedule_count' => 2],
        ])->save();

        $newSlot = $this->firstAvailableSlotInWindow();

        $this->from(route('client_portal.reschedule.show', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]))->post(route('client_portal.reschedule.submit', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]), [
            'starts_at' => $newSlot->toDateTimeString(),
        ])->assertSessionHasErrors();

        $this->assertTrue($entry->fresh()->starts_at->equalTo($entry->starts_at));
    }

    public function test_reschedule_blocks_when_inside_cancellation_window(): void
    {
        // Cancellation policy 12h; booking starts in 6h — too late.
        $entry = $this->makeEntry(now()->addHours(6), CalendarEntryStatus::Confirmed);
        $newSlot = $this->firstAvailableSlotInWindow();

        $this->from(route('client_portal.reschedule.show', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]))->post(route('client_portal.reschedule.submit', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]), [
            'starts_at' => $newSlot->toDateTimeString(),
        ])->assertSessionHasErrors();
    }

    public function test_reschedule_rejects_slot_outside_availability(): void
    {
        Notification::fake();
        $entry = $this->makeEntry(now()->addDays(3)->setTime(10, 0), CalendarEntryStatus::Confirmed);

        // Pick a 3 AM slot — clearly outside working hours 09-19.
        $bogus = now()->addDays(3)->setTime(3, 0);

        $this->from(route('client_portal.reschedule.show', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]))->post(route('client_portal.reschedule.submit', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]), [
            'starts_at' => $bogus->toDateTimeString(),
        ])->assertSessionHasErrors();

        Notification::assertNothingSent();
    }

    public function test_reschedule_unauthenticated_redirects_to_login(): void
    {
        $this->flushSession();
        $entry = $this->makeEntry(now()->addDays(3)->setTime(10, 0), CalendarEntryStatus::Confirmed);

        $this->get(route('client_portal.reschedule.show', [
            'slug' => $this->tenant->slug,
            'entry' => $entry->id,
        ]))->assertRedirect(route('client_portal.login.show', ['slug' => $this->tenant->slug]));
    }

    private function firstAvailableSlotInWindow(): Carbon
    {
        // 5 days out, 11:00 — solidly inside working hours and well past
        // both advance_min_hours and cancellation policy.
        return now()->addDays(5)->setTime(11, 0)->copy();
    }

    private function loginAs(Client $client): void
    {
        $this->session([
            ClientPortalAuth::SESSION_KEY_PREFIX.$this->tenant->slug => [
                'client_id' => $client->id,
                'logged_in_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function makeEntry(
        Carbon $startsAt,
        CalendarEntryStatus $status,
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
            'slug' => 'pv2-'.$u,
            'name' => 'Portal V2 Stable',
            'db_name' => 'pv2_'.$u,
            'db_username' => 'pv2_'.substr($u, -8),
            'status' => 'active',
            'settings' => [
                'public_booking' => [
                    'enabled' => true,
                    'lesson_duration_minutes' => 60,
                    'working_hours_start' => '09:00',
                    'working_hours_end' => '19:00',
                    'advance_min_hours' => 4,
                    'advance_max_days' => 30,
                ],
                'cancellation_policy' => ['hours' => 12],
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
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('central_user_id', 26)->nullable();
            $t->string('magic_link_token_hash', 64)->nullable();
            $t->timestamp('magic_link_expires_at')->nullable();
            $t->timestamp('last_logged_in_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('owner_client_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('instructors', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_user_id', 26)->nullable();
            $t->string('name');
            $t->boolean('is_active')->default(true);
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
            $t->json('metadata')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
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
