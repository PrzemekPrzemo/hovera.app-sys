<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HealthRecordType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Iter. 17c — boarders see their horses on the dashboard and can drill
 * into a read-only health record timeline.
 */
class ClientPortalHorsesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_h_').'.sqlite';
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

    public function test_dashboard_shows_owned_horses(): void
    {
        Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
            'owner_client_id' => $this->client->id,
            'breed' => 'Arabian',
        ]);

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertSee('Twoje konie')
            ->assertSee('Bucefał');
    }

    public function test_dashboard_omits_horses_owned_by_others(): void
    {
        $other = Client::create([
            'id' => '01HCLI0000000000000000999',
            'name' => 'Inny',
        ]);
        Horse::create([
            'id' => '01HHORSE000000000000000888',
            'name' => 'Cudzy',
            'owner_client_id' => $other->id,
        ]);

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $this->assertStringNotContainsString('Cudzy', (string) $response->getContent());
        // No "Twoje konie" section if client has no horses
        $this->assertStringNotContainsString('Twoje konie', (string) $response->getContent());
    }

    public function test_dashboard_aggregates_overdue_and_upcoming_alerts(): void
    {
        $horse = Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
            'owner_client_id' => $this->client->id,
        ]);

        $this->makeRecord($horse, nextDue: now()->subDay());           // overdue
        $this->makeRecord($horse, nextDue: now()->addDays(20));        // upcoming 30
        $this->makeRecord($horse, nextDue: now()->addDays(60));        // out of window
        $this->makeRecord($horse, nextDue: null);                       // no schedule

        $response = $this->get(route('client_portal.dashboard', ['slug' => $this->tenant->slug]));

        $response->assertOk()
            ->assertSee('1 przeterm.')
            ->assertSee('1 w 30 dni');
    }

    public function test_horse_detail_renders_health_history(): void
    {
        $horse = Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
            'owner_client_id' => $this->client->id,
            'breed' => 'Arabian',
        ]);
        $this->makeRecord($horse, summary: 'Szczepienie tężec', performedAt: now()->subMonths(2));

        $response = $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $horse->id,
        ]));

        $response->assertOk()
            ->assertSee('Bucefał')
            ->assertSee('Arabian')
            ->assertSee('Szczepienie tężec');
    }

    public function test_horse_detail_404_for_horse_owned_by_someone_else(): void
    {
        $other = Client::create([
            'id' => '01HCLI0000000000000000999',
            'name' => 'Inny',
        ]);
        $horse = Horse::create([
            'id' => '01HHORSE000000000000000888',
            'name' => 'Cudzy',
            'owner_client_id' => $other->id,
        ]);

        $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $horse->id,
        ]))->assertNotFound();
    }

    public function test_horse_detail_redirects_when_logged_out(): void
    {
        $this->flushSession();
        $horse = Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
            'owner_client_id' => $this->client->id,
        ]);

        $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $horse->id,
        ]))->assertRedirect(route('client_portal.login.show', ['slug' => $this->tenant->slug]));
    }

    private function makeRecord(
        Horse $horse,
        string $summary = 'Test',
        ?Carbon $nextDue = null,
        ?Carbon $performedAt = null,
    ): HealthRecord {
        return HealthRecord::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $horse->id,
            'type' => HealthRecordType::Vaccination->value,
            'performed_at' => $performedAt ?? now()->subDay(),
            'summary' => $summary,
            'next_due_at' => $nextDue?->toDateString(),
        ]);
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
            'slug' => 'h-'.$u,
            'name' => 'Stable',
            'db_name' => 'h_'.$u,
            'db_username' => 'h_'.substr($u, -8),
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
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
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
            $t->string('performed_by', 255)->nullable();
            $t->string('summary', 255);
            $t->text('details')->nullable();
            $t->date('next_due_at')->nullable();
            $t->unsignedInteger('cost_cents')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('boarding_services', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('description', 500)->nullable();
            $t->string('unit', 32)->default('szt.');
            $t->string('frequency', 32);
            $t->unsignedInteger('price_cents');
            $t->string('vat_rate', 8)->default('23');
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horse_boarding_services', function ($t) {
            $t->string('horse_id', 26);
            $t->string('boarding_service_id', 26);
            $t->unsignedInteger('price_override_cents')->nullable();
            $t->decimal('quantity', 10, 3)->default(1);
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->primary(['horse_id', 'boarding_service_id']);
        });

        Schema::connection('tenant')->create('stable_activities', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->timestamp('performed_at');
            $t->string('performed_by', 120)->nullable();
            $t->string('summary', 200)->nullable();
            $t->text('details')->nullable();
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

        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->date('issued_at')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
