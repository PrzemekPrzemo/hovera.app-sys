<?php

declare(strict_types=1);

namespace Tests\Feature\Internal;

use App\Enums\TenantType;
use App\Filament\App\Resources\InternalChannelResource\Pages\ListInternalChannels;
use App\Filament\App\Resources\InternalChannelResource\Pages\ViewInternalChannel;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\InternalChannel;
use App\Models\Tenant\InternalMessage;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PR O5 Channel C (epic 2 UI) — InternalChannelResource: lista kanałów
 * stajni + publikowanie wiadomości przez widok kanału.
 */
class InternalChannelResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_chanui_').'.sqlite';
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

        $this->admin = User::create(['name' => 'Ala Admin', 'email' => 'ala@example.com', 'password' => bcrypt('x')]);
        TenantMembership::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'role' => 'owner']);

        // Bieżący tenant bez rekonfiguracji connection (wzór: BoardingServicesTest).
        $tm = app(TenantManager::class);
        $prop = (new \ReflectionClass($tm))->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $this->tenant);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_list_shows_channels(): void
    {
        $general = InternalChannel::create(['slug' => 'general', 'name' => 'general', 'is_default' => true]);

        Livewire::test(ListInternalChannels::class)
            ->assertCanSeeTableRecords([$general]);
    }

    public function test_post_action_creates_message_with_mentions(): void
    {
        $channel = InternalChannel::create(['slug' => 'general', 'name' => 'general', 'is_default' => true]);

        Livewire::test(ViewInternalChannel::class, ['record' => $channel->id])
            ->callAction('post', data: ['body' => 'pilne @ala zerknij']);

        $message = InternalMessage::query()->where('channel_id', $channel->id)->first();
        $this->assertNotNull($message);
        $this->assertSame($this->admin->id, $message->author_user_id);
        $this->assertSame([$this->admin->id], $message->mentions);
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
            'slug' => 'cui-'.$u,
            'name' => 'Stajnia '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'cui_'.$u,
            'db_username' => 'cui_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
