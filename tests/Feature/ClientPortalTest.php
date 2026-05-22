<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Instructor;
use App\Notifications\ClientPortalMagicLinkNotification;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    private Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_portal_').'.sqlite';
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
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_login_form_renders(): void
    {
        $this->get(route('client_portal.login.show', ['slug' => $this->tenant->slug]))
            ->assertOk()
            ->assertSee('Panel klienta')
            ->assertSee($this->tenant->name);
    }

    public function test_login_submit_with_known_email_sends_magic_link(): void
    {
        Notification::fake();

        $this->post(route('client_portal.login.submit', ['slug' => $this->tenant->slug]), [
            'email' => 'marek@example.com',
        ])
            ->assertOk()
            ->assertSee('marek@example.com');

        Notification::assertSentOnDemand(ClientPortalMagicLinkNotification::class);
        $this->assertNotNull($this->client->fresh()->magic_link_token_hash);
    }

    public function test_login_submit_with_unknown_email_returns_same_page_no_send(): void
    {
        Notification::fake();

        $this->post(route('client_portal.login.submit', ['slug' => $this->tenant->slug]), [
            'email' => 'nobody@example.com',
        ])
            ->assertOk()
            ->assertSee('nobody@example.com');

        Notification::assertNothingSent();
    }

    public function test_login_submit_email_match_is_case_insensitive(): void
    {
        Notification::fake();

        $this->post(route('client_portal.login.submit', ['slug' => $this->tenant->slug]), [
            'email' => 'MAREK@EXAMPLE.com',
        ])->assertOk();

        Notification::assertSentOnDemand(ClientPortalMagicLinkNotification::class);
    }

    public function test_consume_with_valid_token_logs_in_and_redirects_to_dashboard(): void
    {
        Notification::fake();

        $auth = $this->app->make(ClientPortalAuth::class);
        $url = $auth->issueMagicLink($this->client, $this->tenant->slug);

        $this->get($url)
            ->assertRedirect(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        // Token cleared after consume
        $fresh = $this->client->fresh();
        $this->assertNull($fresh->magic_link_token_hash);
        $this->assertNotNull($fresh->last_logged_in_at);
    }

    public function test_consume_with_invalid_token_renders_invalid_page(): void
    {
        $auth = $this->app->make(ClientPortalAuth::class);
        $auth->issueMagicLink($this->client, $this->tenant->slug);

        $bogus = URL::temporarySignedRoute(
            'client_portal.login.consume',
            now()->addMinutes(10),
            ['slug' => $this->tenant->slug, 'client' => $this->client->id, 'token' => 'wrong-token'],
        );

        $this->get($bogus)
            ->assertOk()
            ->assertSee('Link nieaktywny');

        // Original hash still in place — wrong-token guess didn't burn it
        $this->assertNotNull($this->client->fresh()->magic_link_token_hash);
    }

    public function test_consume_with_expired_token_renders_invalid(): void
    {
        $auth = $this->app->make(ClientPortalAuth::class);
        $url = $auth->issueMagicLink($this->client, $this->tenant->slug);

        // Force-age the token past expiry
        $this->client->forceFill(['magic_link_expires_at' => now()->subMinute()])->save();

        // Travel into the future so the URL signature is also expired
        Carbon::setTestNow(now()->addHour());
        $this->get($url)->assertOk()->assertSee('Link nieaktywny');
        Carbon::setTestNow();
    }

    public function test_consume_is_single_use(): void
    {
        $auth = $this->app->make(ClientPortalAuth::class);
        $url = $auth->issueMagicLink($this->client, $this->tenant->slug);

        $this->get($url)->assertRedirect();
        $this->flushSession();

        // Second attempt with the same URL fails — hash was wiped on first use
        $this->get($url)->assertOk()->assertSee('Link nieaktywny');
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]))
            ->assertRedirect(route('client_portal.login.show', ['slug' => $this->tenant->slug]));
    }

    public function test_dashboard_lists_upcoming_and_past_for_current_client(): void
    {
        $this->loginAs($this->client);

        $upcoming = $this->makeEntry(now()->addDays(3), CalendarEntryStatus::Confirmed);
        $past = $this->makeEntry(now()->subDays(2), CalendarEntryStatus::Completed);
        $other = Client::create([
            'id' => '01HCLI0000000000000000999',
            'name' => 'Inny Klient',
            'email' => 'other@example.com',
        ]);
        $hidden = $this->makeEntry(now()->addDays(2), CalendarEntryStatus::Confirmed, clientId: $other->id);

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]))
            ->assertOk()
            ->assertSee($this->client->name);

        $response->assertSee($upcoming->starts_at->format('d.m.Y'));
        $response->assertSee($past->starts_at->format('d.m.Y'));
        // Other client's booking does not leak — would only render if client_id matched.
        $this->assertStringNotContainsString($hidden->id, (string) $response->getContent());
    }

    public function test_logout_clears_session(): void
    {
        $this->loginAs($this->client);

        $this->post(route('client_portal.logout', ['slug' => $this->tenant->slug]))
            ->assertRedirect(route('client_portal.login.show', ['slug' => $this->tenant->slug]));

        $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]))
            ->assertRedirect(route('client_portal.login.show', ['slug' => $this->tenant->slug]));
    }

    public function test_unknown_tenant_slug_404s(): void
    {
        $this->get('/s/nope/portal/login')->assertNotFound();
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
            'slug' => 'portal-'.$u,
            'name' => 'Portal Stable',
            'db_name' => 'portal_'.$u,
            'db_username' => 'portal_'.substr($u, -8),
            'status' => 'active',
            'settings' => [
                'public_profile' => ['address' => 'ul. Kasztanowa 7'],
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

        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('performed_by', 255)->nullable();
            $t->string('summary', 255);
            $t->text('details')->nullable();
            $t->date('next_due_at')->nullable();
            $t->unsignedInteger('cost_cents')->nullable();
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

        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->char('seller_country', 2)->default('PL');
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->char('buyer_country', 2)->default('PL');
            $t->string('buyer_type', 16)->default('individual');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
