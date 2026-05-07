<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\ClientMessage;
use App\Services\Portal\ClientMessageJournal;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Iter. 17d — Notifications hub: dashboard recent-list, dedicated
 * full-history page, journal record() invariants.
 */
class ClientPortalMessagesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_msg_').'.sqlite';
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
            'name' => 'Marek',
            'email' => 'marek@example.com',
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

    public function test_journal_record_writes_to_tenant_db(): void
    {
        $journal = app(ClientMessageJournal::class);

        $msg = $journal->record(
            $this->client,
            'booking.confirmed',
            'Test subject',
            ['foo' => 'bar'],
            'CalendarEntry',
            '01HENTRY00000000000000001',
        );

        $this->assertNotNull($msg);
        $this->assertSame('marek@example.com', $msg->to_email);
        $this->assertSame(['foo' => 'bar'], $msg->preview);
        $this->assertSame(1, ClientMessage::query()->count());
    }

    public function test_journal_skips_when_client_has_no_email(): void
    {
        $silent = Client::create([
            'id' => '01HCLI0000000000000000999',
            'name' => 'Bez emaila',
        ]);

        $msg = app(ClientMessageJournal::class)->record($silent, 'portal.magic_link', 'x');

        $this->assertNull($msg);
        $this->assertSame(0, ClientMessage::query()->count());
    }

    public function test_dashboard_lists_recent_messages(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            ClientMessage::create([
                'id' => (string) Str::ulid(),
                'client_id' => $this->client->id,
                'type' => 'booking.confirmed',
                'subject' => 'Wiadomość '.$i,
                'to_email' => $this->client->email,
                'sent_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertSee('Wiadomości')
            ->assertSee('Wiadomość 1')
            ->assertSee('Wiadomość 5')
            // Only top 5, so #6 / #7 don't render in dashboard preview
            ->assertDontSee('Wiadomość 6')
            ->assertDontSee('Wiadomość 7');
    }

    public function test_messages_page_paginates_full_history(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            ClientMessage::create([
                'id' => (string) Str::ulid(),
                'client_id' => $this->client->id,
                'type' => 'booking.reminder',
                'subject' => 'Old '.$i,
                'to_email' => $this->client->email,
                'sent_at' => now()->subDays($i),
            ]);
        }

        $response = $this->get(route('client_portal.messages.show', ['slug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertSee('Wiadomości')
            ->assertSee('Old 1')
            // Page size is 30 — last 5 should be on page 2
            ->assertDontSee('Old 31');
    }

    public function test_messages_page_isolates_by_client(): void
    {
        $other = Client::create([
            'id' => '01HCLI0000000000000000777',
            'name' => 'Inny',
            'email' => 'inny@example.com',
        ]);
        ClientMessage::create([
            'id' => (string) Str::ulid(),
            'client_id' => $other->id,
            'type' => 'booking.confirmed',
            'subject' => 'Wiadomość cudzy',
            'to_email' => $other->email,
            'sent_at' => now()->subHour(),
        ]);

        $response = $this->get(route('client_portal.messages.show', ['slug' => $this->tenant->slug]));

        $this->assertStringNotContainsString('cudzy', strtolower((string) $response->getContent()));
    }

    public function test_messages_page_requires_login(): void
    {
        $this->flushSession();

        $this->get(route('client_portal.messages.show', ['slug' => $this->tenant->slug]))
            ->assertRedirect(route('client_portal.login.show', ['slug' => $this->tenant->slug]));
    }

    public function test_message_label_humanises_unknown_type(): void
    {
        $msg = ClientMessage::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'type' => 'invoice.issued',
            'subject' => 'FV',
            'to_email' => $this->client->email,
            'sent_at' => now(),
        ]);

        $this->assertSame('invoice · issued', $msg->label());
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

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'msg-'.$u,
            'name' => 'Stable',
            'db_name' => 'msg_'.$u,
            'db_username' => 'msg_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
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
            $t->string('status', 32);
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
            $t->date('valid_until')->nullable();
            $t->string('status', 32)->default('active');
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

        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('summary', 255);
            $t->date('next_due_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('client_messages', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('client_id', 26);
            $t->string('type', 64);
            $t->string('subject', 255);
            $t->string('to_email', 255);
            $t->json('preview')->nullable();
            $t->string('related_type', 60)->nullable();
            $t->string('related_id', 26)->nullable();
            $t->timestamp('sent_at');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
