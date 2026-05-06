<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Calendar\RequestPublicBooking;
use App\Actions\Calendar\UpdateCalendarEntry;
use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Instructor;
use App\Notifications\BookingCancelledClientNotification;
use App\Notifications\BookingConfirmedClientNotification;
use App\Notifications\BookingRequestedClientNotification;
use App\Services\Calendar\BookingCancellationLink;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class BookingCancellationFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_bcf_').'.sqlite';
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

    public function test_request_dispatches_client_notification_with_cancel_url(): void
    {
        Notification::fake();

        $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        Notification::assertSentOnDemand(
            BookingRequestedClientNotification::class,
            function (BookingRequestedClientNotification $notification, array $channels, $notifiable) {
                return $notification->tenantName === $this->tenant->name
                    && str_starts_with($notification->cancelUrl, 'http')
                    && $notification->cancellationPolicyHours > 0;
            }
        );
    }

    public function test_confirmation_by_owner_dispatches_confirmed_mail(): void
    {
        Notification::fake();

        $req = $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        // Owner: assign horse + confirm
        $this->app->make(UpdateCalendarEntry::class)->execute($req['entry'], [
            'horse_id' => '01HHORSE0000000000000000A1',
            'status' => CalendarEntryStatus::Confirmed->value,
        ]);

        Notification::assertSentOnDemand(BookingConfirmedClientNotification::class);
    }

    public function test_owner_cancellation_dispatches_cancelled_mail(): void
    {
        Notification::fake();

        $req = $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        $this->app->make(UpdateCalendarEntry::class)->execute($req['entry'], [
            'horse_id' => '01HHORSE0000000000000000A1',
            'status' => CalendarEntryStatus::Confirmed->value,
        ]);

        $this->app->make(UpdateCalendarEntry::class)->execute(
            $req['entry']->fresh(),
            ['status' => CalendarEntryStatus::Cancelled->value],
        );

        Notification::assertSentOnDemand(BookingCancelledClientNotification::class);
    }

    public function test_signed_cancel_url_renders_form_for_valid_signature(): void
    {
        Notification::fake();

        $req = $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        $url = $this->app->make(BookingCancellationLink::class)
            ->for($req['entry'], $this->tenant->slug);

        $this->get($url)
            ->assertOk()
            ->assertSee('Odwołaj rezerwację');
    }

    public function test_unsigned_cancel_url_shows_invalid_page(): void
    {
        Notification::fake();
        $req = $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        // Plain URL without signature
        $url = "/s/{$this->tenant->slug}/book/cancel/{$req['entry']->id}";

        $this->get($url)
            ->assertOk()
            ->assertSee('Link wygasł');
    }

    public function test_cancel_post_marks_entry_cancelled_and_emails_client(): void
    {
        Notification::fake();
        $req = $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        $url = $this->app->make(BookingCancellationLink::class)
            ->for($req['entry'], $this->tenant->slug);

        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $response = $this->post($parts['path'].'?'.$parts['query']);

        $response->assertOk()->assertSee('Rezerwacja odwołana');

        $this->assertSame(
            CalendarEntryStatus::Cancelled,
            $req['entry']->fresh()->status,
        );

        Notification::assertSentOnDemand(
            BookingCancelledClientNotification::class,
            fn (BookingCancelledClientNotification $n) => $n->cancelledBy === 'client',
        );
    }

    public function test_cancel_post_idempotent_on_already_cancelled_entry(): void
    {
        Notification::fake();
        $req = $this->app->make(RequestPublicBooking::class)->execute($this->tenant, [
            'instructor_id' => $this->instructor->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'name' => 'Anna',
            'email' => 'anna@example.com',
        ]);

        // Pre-cancel server-side
        $req['entry']->forceFill(['status' => CalendarEntryStatus::Cancelled->value])->save();

        $url = $this->app->make(BookingCancellationLink::class)
            ->for($req['entry'], $this->tenant->slug);

        // Should redirect (no second cancel)
        $this->get($url)
            ->assertOk()
            ->assertSee('Rezerwacja już odwołana');
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'bcf-'.$u,
            'name' => 'BCF Test',
            'db_name' => 'bcf_'.$u,
            'db_username' => 'bcf_'.substr($u, -8),
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
            $t->string('owner_client_id', 26)->nullable();
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 15)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 32)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('cover_image_path')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

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
