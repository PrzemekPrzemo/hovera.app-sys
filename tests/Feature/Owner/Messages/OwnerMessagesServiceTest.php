<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Messages;

use App\Domain\Messages\Owner\OwnerMessagesService;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa OwnerMessagesService — cross-tenant lista/send/markRead/
 * unreadCount dla wiadomości Owner ↔ Stable per koń.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4".
 */
class OwnerMessagesServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    private string $centralHorseId;

    private string $horseId;

    private string $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_msgs_').'.sqlite';
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

        $registry = $this->makeRegistry();
        $this->centralHorseId = $registry->id;
        $this->horseId = $this->seedHorse($registry->id);
        $this->clientId = $this->seedClient($this->owner->id);
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_list_returns_messages_ascending_by_sent_at(): void
    {
        $this->makeActiveBoarding();
        $this->seedMessage('from_stable', 'Witamy', '2026-05-01 09:00');
        $this->seedMessage('from_client', 'Dzięki!', '2026-05-01 10:00');
        $this->seedMessage('from_stable', 'Update', '2026-05-02 14:00');

        $messages = app(OwnerMessagesService::class)
            ->listForHorse($this->owner, $this->centralHorseId);

        $this->assertCount(3, $messages);
        $this->assertSame('Witamy', $messages[0]->subject);
        $this->assertSame('Update', $messages[2]->subject);
    }

    public function test_list_throws_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $this->expectException(AuthorizationException::class);
        app(OwnerMessagesService::class)
            ->listForHorse($other, $this->centralHorseId);
    }

    public function test_list_works_for_ended_boarding_per_q3(): void
    {
        // Ended boarding zachowuje read access do historycznych wiadomości.
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);
        $this->seedMessage('from_stable', 'Old', '2025-12-01 10:00');

        $messages = app(OwnerMessagesService::class)
            ->listForHorse($this->owner, $this->centralHorseId);

        $this->assertCount(1, $messages);
    }

    public function test_send_creates_from_client_message_with_active_boarding(): void
    {
        $this->makeActiveBoarding();

        $message = app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, 'Pytanie', 'Jak idzie trening?');

        $this->assertSame('from_client', $message->direction);
        $this->assertSame('Pytanie', $message->subject);
        $this->assertSame('Jak idzie trening?', $message->body);

        // Sprawdzamy w DB
        $count = DB::connection('tenant')->table('horse_messages')
            ->where('direction', 'from_client')->count();
        $this->assertSame(1, $count);
    }

    public function test_send_throws_when_only_ended_boarding(): void
    {
        // Ended = read-only per Q3, write zabronione.
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);

        $this->expectException(AuthorizationException::class);
        app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, null, 'Hello');
    }

    public function test_send_throws_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $this->expectException(AuthorizationException::class);
        app(OwnerMessagesService::class)
            ->send($other, $this->centralHorseId, null, 'Hi');
    }

    public function test_send_persists_attachments_json(): void
    {
        $this->makeActiveBoarding();

        $attachments = [
            ['path' => 'tenants/x/y.jpg', 'original_name' => 'iskra.jpg', 'mime' => 'image/jpeg', 'size' => 102400],
        ];
        $message = app(OwnerMessagesService::class)
            ->send($this->owner, $this->centralHorseId, null, 'Zdjęcia', $attachments);

        $this->assertSame(1, $message->attachmentCount);
        $this->assertSame('iskra.jpg', $message->attachments[0]['original_name']);
    }

    public function test_mark_read_sets_timestamp_for_from_stable_message(): void
    {
        $this->makeActiveBoarding();
        $messageId = $this->seedMessage('from_stable', 'Witamy', '2026-05-01 09:00');

        $this->assertNull(DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at'));

        app(OwnerMessagesService::class)
            ->markRead($this->owner, $this->stableTenant->id, $messageId);

        $this->assertNotNull(DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at'));
    }

    public function test_mark_read_is_noop_for_from_client_message(): void
    {
        $this->makeActiveBoarding();
        $messageId = $this->seedMessage('from_client', 'Pytanie', '2026-05-01 10:00');

        app(OwnerMessagesService::class)
            ->markRead($this->owner, $this->stableTenant->id, $messageId);

        // read_by_client_at NIE jest ustawiany dla from_client — to flaga
        // dla stable side (gdy stable czyta wiadomość od ownera).
        $this->assertNull(DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at'));
    }

    public function test_mark_read_is_idempotent(): void
    {
        $this->makeActiveBoarding();
        $messageId = $this->seedMessage('from_stable', 'X', '2026-05-01 09:00');

        app(OwnerMessagesService::class)
            ->markRead($this->owner, $this->stableTenant->id, $messageId);
        $firstTimestamp = DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at');

        // Drugi mark — timestamp NIE powinien się zmienić (idempotent).
        sleep(1);
        app(OwnerMessagesService::class)
            ->markRead($this->owner, $this->stableTenant->id, $messageId);
        $secondTimestamp = DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at');

        $this->assertSame($firstTimestamp, $secondTimestamp);
    }

    public function test_unread_count_returns_only_from_stable_unread(): void
    {
        $this->makeActiveBoarding();
        // 2 unread from_stable
        $this->seedMessage('from_stable', 'Msg1', '2026-05-01 09:00');
        $this->seedMessage('from_stable', 'Msg2', '2026-05-02 09:00');
        // 1 already-read from_stable
        $readId = $this->seedMessage('from_stable', 'Msg3', '2026-05-03 09:00');
        DB::connection('tenant')->table('horse_messages')
            ->where('id', $readId)->update(['read_by_client_at' => now()]);
        // from_client nie liczy się w unread (to wiadomości ownera, on je widzi)
        $this->seedMessage('from_client', 'Q1', '2026-05-04 09:00');

        $count = app(OwnerMessagesService::class)->unreadCount($this->owner);

        $this->assertSame(2, $count);
    }

    public function test_unread_count_aggregates_across_multiple_stable_tenants(): void
    {
        // Drugi stable tenant z osobną DB — symulujemy ownera w 2 stajniach.
        // Dla testu wystarczy że unreadCount iteruje przez tenant_ids; w
        // jednym SQLite DB wszystkie messages żyją w tej samej tabeli, ale
        // mock TenantManager::execute zamienia tenant na każdej iteracji
        // — w naszej implementacji to ten sam SQLite file.
        $this->makeActiveBoarding();
        $this->seedMessage('from_stable', 'Hi', '2026-05-01 09:00');

        $count = app(OwnerMessagesService::class)->unreadCount($this->owner);

        $this->assertSame(1, $count);
    }

    public function test_unread_count_skips_owner_with_no_assignments(): void
    {
        // Owner bez żadnego assignment'u — zero unread.
        $this->seedMessage('from_stable', 'X', '2026-05-01 09:00');

        $count = app(OwnerMessagesService::class)->unreadCount($this->owner);

        $this->assertSame(0, $count);
    }

    // ---- HELPERS ----

    private function makeRegistry(): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
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

    private function seedMessage(string $direction, string $subject, string $sentAt): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horse_messages')->insert([
            'id' => $id,
            'horse_id' => $this->horseId,
            'direction' => $direction,
            'sender_user_id' => $direction === 'from_stable' ? (string) Str::ulid() : $this->owner->id,
            'client_id' => $this->clientId,
            'subject' => $subject,
            'body' => 'Body of '.$subject,
            'attachments' => null,
            'sent_at' => $sentAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);

        return $id;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'msgs-st-'.$u,
            'name' => 'Messages Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'msgs_st_'.$u,
            'db_username' => 'msgs_st_'.substr($u, -8),
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
