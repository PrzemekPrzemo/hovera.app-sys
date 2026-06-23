<?php

declare(strict_types=1);

namespace Tests\Feature\Internal;

use App\Enums\TenantType;
use App\Jobs\Internal\SendDailyDigestJob;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\InternalChannel;
use App\Models\Tenant\InternalChannelMember;
use App\Models\Tenant\InternalMessage;
use App\Notifications\Internal\InternalDailyDigestNotification;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR O5 Channel C (epic 2) — SendDailyDigestJob: agreguje unread per user,
 * grupuje po kanale, pomija userów z 0 unread.
 */
class SendDailyDigestJobTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private User $anna;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_digest_').'.sqlite';
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

        $this->anna = User::create(['name' => 'Anna', 'email' => 'anna@example.com', 'password' => bcrypt('x')]);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('x')]);

        // Mock TenantManager — execute() bez rekonfiguracji connection.
        $held = null;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
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
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_digest_sent_only_to_users_with_unread(): void
    {
        Notification::fake();

        $general = $this->channel('general');
        $vet = $this->channel('weterynaria');
        $this->member($general, $this->anna->id);
        $this->member($general, $this->bob->id);
        $this->member($vet, $this->anna->id);

        // Bob pisze 2 wiadomości na #general, 1 na #weterynaria → unread dla Anny.
        $this->message($general, $this->bob->id);
        $this->message($general, $this->bob->id);
        $this->message($vet, $this->bob->id);
        // Anna pisze 1 na #general → to NIE jest unread dla niej samej, ale jest dla Boba.
        $this->message($general, $this->anna->id);

        (new SendDailyDigestJob)->handle(app(TenantManager::class));

        // Anna: 2 (#general) + 1 (#weterynaria) = 3 unread.
        Notification::assertSentTo(
            $this->anna,
            InternalDailyDigestNotification::class,
            function (InternalDailyDigestNotification $n) {
                return $n->total === 3 && count($n->groups) === 2;
            },
        );

        // Bob: 1 (#general, od Anny) unread.
        Notification::assertSentTo(
            $this->bob,
            InternalDailyDigestNotification::class,
            fn (InternalDailyDigestNotification $n) => $n->total === 1 && count($n->groups) === 1,
        );
    }

    public function test_user_with_zero_unread_is_skipped(): void
    {
        Notification::fake();

        $general = $this->channel('general');
        $this->member($general, $this->anna->id);
        $this->member($general, $this->bob->id);

        // Tylko Anna pisze — Bob ma unread, Anna nie.
        $this->message($general, $this->anna->id);

        (new SendDailyDigestJob)->handle(app(TenantManager::class));

        Notification::assertSentTo($this->bob, InternalDailyDigestNotification::class);
        Notification::assertNotSentTo($this->anna, InternalDailyDigestNotification::class);
    }

    public function test_messages_older_than_24h_are_excluded(): void
    {
        Notification::fake();

        $general = $this->channel('general');
        $this->member($general, $this->anna->id);

        $old = $this->message($general, $this->bob->id);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        (new SendDailyDigestJob)->handle(app(TenantManager::class));

        Notification::assertNothingSent();
    }

    private function channel(string $slug): InternalChannel
    {
        return InternalChannel::create([
            'slug' => $slug,
            'name' => $slug,
            'is_default' => true,
        ]);
    }

    private function member(InternalChannel $channel, string $userId): void
    {
        InternalChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $userId,
            'joined_at' => now()->subDays(10),
            'notifications_enabled' => true,
        ]);
    }

    private function message(InternalChannel $channel, string $authorId): InternalMessage
    {
        return InternalMessage::create([
            'id' => (string) Str::ulid(),
            'channel_id' => $channel->id,
            'author_user_id' => $authorId,
            'body' => 'treść',
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('internal_channels', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('slug', 60)->unique();
            $t->string('name', 120);
            $t->string('description', 500)->nullable();
            $t->boolean('is_default')->default(false);
            $t->string('created_by_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('internal_channel_members', function ($t) {
            $t->string('channel_id', 26);
            $t->string('user_id', 26);
            $t->timestamp('joined_at')->useCurrent();
            $t->boolean('notifications_enabled')->default(true);
            $t->timestamp('last_read_at')->nullable();
            $t->primary(['channel_id', 'user_id']);
        });

        Schema::connection('tenant')->create('internal_messages', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('channel_id', 26);
            $t->string('author_user_id', 26);
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->json('mentions')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'dg-'.$u,
            'name' => 'Stajnia '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'dg_'.$u,
            'db_username' => 'dg_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
