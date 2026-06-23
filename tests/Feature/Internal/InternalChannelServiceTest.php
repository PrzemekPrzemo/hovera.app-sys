<?php

declare(strict_types=1);

namespace Tests\Feature\Internal;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\InternalChannel;
use App\Services\Internal\InternalChannelService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PR O5 Channel C (epic 2) — InternalChannelService: seed domyślnych
 * kanałów, członkostwo, @mention extraction, publikacja wiadomości.
 */
class InternalChannelServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private User $anna;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_chan_').'.sqlite';
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

        $this->anna = User::create([
            'name' => 'Anna Kowalska',
            'email' => 'anna@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->bob = User::create([
            'name' => 'Bob Nowak',
            'email' => 'bob@example.com',
            'password' => bcrypt('secret'),
        ]);

        foreach ([$this->anna, $this->bob] as $u) {
            TenantMembership::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $u->id,
                'role' => 'manager',
            ]);
        }

        // Ustaw bieżący tenant bez rekonfiguracji connection (setCurrent
        // przełączyłby na MySQL z databaseConnectionConfig). Wzór: BoardingServicesTest.
        $tm = app(TenantManager::class);
        $prop = (new \ReflectionClass($tm))->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $this->tenant);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_ensure_defaults_creates_three_channels_with_members(): void
    {
        $created = $this->service()->ensureDefaultsFor();

        $this->assertSame(3, $created);
        $this->assertSame(3, InternalChannel::count());
        $this->assertEqualsCanonicalizing(
            ['general', 'weterynaria', 'transport'],
            InternalChannel::pluck('slug')->all(),
        );

        $general = InternalChannel::where('slug', 'general')->first();
        $this->assertTrue($general->is_default);
        $this->assertSame(2, $general->members()->count());
    }

    public function test_ensure_defaults_is_idempotent(): void
    {
        $this->service()->ensureDefaultsFor();
        $secondRun = $this->service()->ensureDefaultsFor();

        $this->assertSame(0, $secondRun);
        $this->assertSame(3, InternalChannel::count());
        $this->assertSame(2, InternalChannel::where('slug', 'general')->first()->members()->count());
    }

    public function test_extract_mentions_resolves_by_email_local_and_name_slug(): void
    {
        $service = $this->service();

        $this->assertSame([$this->anna->id], $service->extractMentions('hej @anna jak tam?'));
        $this->assertSame([$this->anna->id], $service->extractMentions('cześć @annakowalska'));
        $this->assertEqualsCanonicalizing(
            [$this->anna->id, $this->bob->id],
            $service->extractMentions('@anna i @bob do roboty'),
        );
        $this->assertSame([], $service->extractMentions('@nieistnieje halo'));
        $this->assertSame([], $service->extractMentions('bez wzmianek'));
    }

    public function test_post_message_persists_mentions(): void
    {
        $this->service()->ensureDefaultsFor();
        $channel = InternalChannel::where('slug', 'general')->first();

        $message = $this->service()->postMessage($channel, $this->bob->id, 'pilne @anna', []);

        $this->assertSame($channel->id, $message->channel_id);
        $this->assertSame($this->bob->id, $message->author_user_id);
        $this->assertSame([$this->anna->id], $message->mentions);
    }

    private function service(): InternalChannelService
    {
        return app(InternalChannelService::class);
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
            'slug' => 'chan-'.$u,
            'name' => 'Stajnia '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'chan_'.$u,
            'db_username' => 'chan_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
