<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Messages;

use App\Domain\Messages\Owner\OwnerMessagesService;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Notifications\OwnerSentMessageToStableNotification;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Faza 4 PR 4.4 — po POST send() przez ownera, stable team
 * (operator/admin/owner/manager) dostaje OwnerSentMessageToStableNotification
 * przez database + mail.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4 PR 4.4".
 */
class OwnerSendNotifiesStableTeamTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    private string $centralHorseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_msgsnot_').'.sqlite';
        touch($this->stableDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpStableSchema();
        $this->stableTenant = $this->makeStableTenant();
        $this->owner = User::create([
            'name' => 'Jan Owner',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);

        $held = null;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(fn () => $held);
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
            $m->shouldReceive('execute')->andReturnUsing(function (Tenant $t, callable $cb) use (&$held) {
                $prev = $held;
                $held = $t;
                try {
                    return $cb($t);
                } finally {
                    $held = $prev;
                }
            });
        });

        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->centralHorseId = $registry->id;
        $this->seedHorse($this->centralHorseId);
        $this->seedClient($this->owner->id);
        $this->makeActiveBoarding();
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_send_notifies_all_team_members_with_eligible_roles(): void
    {
        Notification::fake();

        $opUser = $this->makeUser('operator');
        $this->addMembership($opUser, 'operator');
        $adminUser = $this->makeUser('admin');
        $this->addMembership($adminUser, 'admin');
        $managerUser = $this->makeUser('manager');
        $this->addMembership($managerUser, 'manager');

        app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, 'Pytanie', 'Hej, kiedy będzie wet?');

        Notification::assertSentTo($opUser, OwnerSentMessageToStableNotification::class);
        Notification::assertSentTo($adminUser, OwnerSentMessageToStableNotification::class);
        Notification::assertSentTo($managerUser, OwnerSentMessageToStableNotification::class);
    }

    public function test_send_does_not_notify_ineligible_roles(): void
    {
        Notification::fake();

        $instructor = $this->makeUser('instructor');
        $this->addMembership($instructor, 'instructor');
        $employee = $this->makeUser('employee');
        $this->addMembership($employee, 'employee');
        $driver = $this->makeUser('driver');
        $this->addMembership($driver, 'driver');

        app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, null, 'Hi');

        Notification::assertNotSentTo($instructor, OwnerSentMessageToStableNotification::class);
        Notification::assertNotSentTo($employee, OwnerSentMessageToStableNotification::class);
        Notification::assertNotSentTo($driver, OwnerSentMessageToStableNotification::class);
    }

    public function test_send_does_not_notify_revoked_membership(): void
    {
        Notification::fake();

        $exOperator = $this->makeUser('ex-operator');
        TenantMembership::create([
            'tenant_id' => $this->stableTenant->id,
            'user_id' => $exOperator->id,
            'role' => 'operator',
            'joined_at' => now()->subYear(),
            'revoked_at' => now()->subDays(30),
        ]);

        app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, null, 'Hello');

        Notification::assertNotSentTo($exOperator, OwnerSentMessageToStableNotification::class);
    }

    public function test_notification_payload_carries_owner_horse_subject_attachments(): void
    {
        Notification::fake();

        $operator = $this->makeUser('operator');
        $this->addMembership($operator, 'operator');

        app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, 'Wizyta wet', 'Możemy umówić wizytę?', [
                ['path' => 'x', 'original_name' => 'opis.pdf', 'mime' => 'application/pdf', 'size' => 1024],
            ]);

        Notification::assertSentTo($operator, OwnerSentMessageToStableNotification::class,
            function (OwnerSentMessageToStableNotification $notification) {
                $this->assertSame('Jan Owner', $notification->ownerName);
                $this->assertSame('Iskra', $notification->horseName);
                $this->assertSame('Wizyta wet', $notification->subject);
                $this->assertStringContainsString('Możemy umówić wizytę', $notification->bodyPreview);
                $this->assertSame(1, $notification->attachmentCount);

                return true;
            },
        );
    }

    public function test_send_succeeds_even_when_no_team_members(): void
    {
        Notification::fake();
        // Brak żadnych TenantMembership w stable.

        $snapshot = app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, null, 'Hello');

        // Send powinien zwrócić snapshot bez wyjątków.
        $this->assertSame('Hello', $snapshot->body);
        Notification::assertNothingSent();
    }

    public function test_truncates_body_preview_to_280_chars(): void
    {
        Notification::fake();
        $operator = $this->makeUser('op');
        $this->addMembership($operator, 'operator');

        $longBody = str_repeat('Lorem ipsum dolor sit amet. ', 30); // ~840 chars

        app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, null, $longBody);

        Notification::assertSentTo($operator, OwnerSentMessageToStableNotification::class,
            function (OwnerSentMessageToStableNotification $notification): bool {
                $this->assertLessThanOrEqual(280, mb_strlen($notification->bodyPreview));
                $this->assertStringEndsWith('…', $notification->bodyPreview);

                return true;
            },
        );
    }

    public function test_notification_via_channels_includes_database_and_mail(): void
    {
        $notification = new OwnerSentMessageToStableNotification(
            ownerName: 'Jan',
            horseName: 'Iskra',
            stableHorseId: 'x',
            subject: 'Test',
            bodyPreview: 'preview',
            attachmentCount: 0,
            stableHorseUrl: 'https://example.com/x',
        );

        $channels = $notification->via(new \stdClass);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_notification_to_database_payload_structure(): void
    {
        $notification = new OwnerSentMessageToStableNotification(
            ownerName: 'Jan',
            horseName: 'Iskra',
            stableHorseId: 'horse-id-x',
            subject: 'Hi',
            bodyPreview: 'short body',
            attachmentCount: 2,
            stableHorseUrl: 'https://example.com/x',
        );

        $payload = $notification->toDatabase(new \stdClass);
        $this->assertSame('owner_message_to_stable', $payload['kind']);
        $this->assertSame('Jan', $payload['owner_name']);
        $this->assertSame('Iskra', $payload['horse_name']);
        $this->assertSame('horse-id-x', $payload['stable_horse_id']);
        $this->assertSame(2, $payload['attachment_count']);
    }

    // ---- HELPERS ----

    private function makeUser(string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => $label.'-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
    }

    private function addMembership(User $user, string $role): void
    {
        TenantMembership::create([
            'tenant_id' => $this->stableTenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    private function makeActiveBoarding(): HorseBoardingAssignment
    {
        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subMonths(3),
        ]);
    }

    private function seedHorse(string $centralHorseId): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert([
            'id' => $id,
            'central_horse_id' => $centralHorseId,
            'name' => 'Iskra',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedClient(string $centralUserId): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $id,
            'name' => 'Jan Owner',
            'central_user_id' => $centralUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'notif-st-'.$u,
            'name' => 'Notif Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'notif_st_'.$u,
            'db_username' => 'notif_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function setUpStableSchema(): void
    {
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable();
            $t->string('name', 120);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 24)->default('individual');
            $t->string('name', 200);
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('central_user_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('horse_messages', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('direction', 32);
            $t->string('sender_user_id', 26)->nullable();
            $t->string('client_id', 26);
            $t->string('subject', 200)->nullable();
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->timestamp('sent_at');
            $t->timestamp('read_by_client_at')->nullable();
            $t->timestamp('read_by_stable_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
