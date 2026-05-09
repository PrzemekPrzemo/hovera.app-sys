<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BoardingFrequency;
use App\Enums\StableActivityType;
use App\Models\Central\Tenant;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Box;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\StableActivity;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class BoardingServicesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    private Horse $horse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_brd_').'.sqlite';
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
        $this->horse = Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
            'owner_client_id' => $this->client->id,
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

    public function test_boarding_service_monthly_equivalent_for_daily_frequency(): void
    {
        $service = $this->makeService(['frequency' => 'daily', 'price_cents' => 500]); // 5 zł/dzień
        // ~30 dni miesiąc
        $this->assertSame(15000, $service->monthlyEquivalentCents());
    }

    public function test_boarding_service_monthly_equivalent_for_per_use_is_zero(): void
    {
        $service = $this->makeService(['frequency' => 'per_use', 'price_cents' => 5000]);
        $this->assertSame(0, $service->monthlyEquivalentCents());
    }

    public function test_horse_estimated_monthly_cost_sums_box_and_services(): void
    {
        $box = Box::create([
            'id' => (string) Str::ulid(),
            'name' => 'B-1',
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => true,
            'monthly_rate_cents' => 80000, // 800 zł
        ]);
        $this->horse->forceFill(['box_id' => $box->id])->save();

        $hay = $this->makeService(['name' => 'Siano', 'frequency' => 'daily', 'price_cents' => 300]); // 3 zł/dzień
        $cleaning = $this->makeService(['name' => 'Sprzątanie', 'frequency' => 'monthly', 'price_cents' => 15000]); // 150 zł/mies.
        $transport = $this->makeService(['name' => 'Transport', 'frequency' => 'per_use', 'price_cents' => 30000]);

        $this->horse->boardingServices()->attach([
            $hay->id => ['quantity' => 5], // 5 kg/dzień, qty=5
            $cleaning->id => ['quantity' => 1],
            $transport->id => ['quantity' => 1],
        ]);

        $total = $this->horse->refresh()->estimatedMonthlyCostCents();
        // box 80000 + siano (300 × 5 × 30 = 45000) + sprzątanie (15000) + transport (0)
        $this->assertSame(80000 + 45000 + 15000, $total);
    }

    public function test_horse_estimated_cost_uses_price_override(): void
    {
        $service = $this->makeService(['frequency' => 'monthly', 'price_cents' => 20000]); // 200 zł
        // Klient wynegocjował 150 zł — pivot price_override
        $this->horse->boardingServices()->attach($service->id, ['price_override_cents' => 15000, 'quantity' => 1]);

        $total = $this->horse->refresh()->estimatedMonthlyCostCents();
        $this->assertSame(15000, $total);
    }

    public function test_stable_activity_can_be_logged_for_horse(): void
    {
        $activity = StableActivity::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'type' => StableActivityType::Feeding->value,
            'performed_at' => now()->subHours(2),
            'performed_by' => 'Anna',
            'summary' => 'Karmienie wieczorne — siano + owies',
        ]);

        $this->assertSame(StableActivityType::Feeding, $activity->type);
        $this->assertCount(1, $this->horse->activities);
    }

    public function test_recent_scope_filters_by_days(): void
    {
        StableActivity::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'type' => StableActivityType::Feeding->value,
            'performed_at' => now()->subDays(5),
            'summary' => 'Świeże',
        ]);
        StableActivity::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'type' => StableActivityType::Feeding->value,
            'performed_at' => now()->subDays(60),
            'summary' => 'Stare',
        ]);

        $recent = StableActivity::query()->forHorse($this->horse->id)->recent(30)->get();
        $this->assertCount(1, $recent);
        $this->assertSame('Świeże', $recent->first()->summary);
    }

    public function test_portal_horse_detail_shows_box_and_services(): void
    {
        $box = Box::create([
            'id' => (string) Str::ulid(),
            'name' => 'Box A',
            'label' => 'A',
            'type' => 'indoor',
            'capacity' => 1,
            'monthly_rate_cents' => 60000,
            'is_active' => true,
        ]);
        $this->horse->forceFill(['box_id' => $box->id])->save();

        $hay = $this->makeService(['name' => 'Siano premium', 'frequency' => 'daily', 'price_cents' => 400]);
        $this->horse->boardingServices()->attach($hay->id, ['quantity' => 5]);

        $this->loginAs($this->client);

        $response = $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]));

        $response->assertOk()
            ->assertSee('Box A')                          // box label/name
            ->assertSee('Pensja i koszty')                // sekcja
            ->assertSee('Siano premium')                  // service name
            ->assertSee('600,00 zł')                      // box rate (600 zł = 60000 gr)
            ->assertSee('Szacunkowy koszt miesięczny');   // summary
    }

    public function test_portal_horse_detail_shows_recent_activities(): void
    {
        StableActivity::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'type' => StableActivityType::Turnout->value,
            'performed_at' => now()->subHours(3),
            'summary' => 'Padok wschodni 9:00-12:00',
            'performed_by' => 'Anna',
        ]);

        $this->loginAs($this->client);

        $response = $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]));

        $response->assertOk()
            ->assertSee('Co robimy z Twoim koniem')
            ->assertSee('Padok wschodni')
            ->assertSee('Anna')
            ->assertSee('Wypuszczenie na padok'); // type label
    }

    public function test_portal_hides_pension_section_when_no_box_no_services(): void
    {
        // Bare horse — no box, no services
        $this->loginAs($this->client);

        $response = $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]));

        $response->assertOk()
            ->assertDontSee('Pensja i koszty')
            ->assertDontSee('Co robimy z Twoim koniem');
    }

    public function test_other_clients_horse_returns_404_even_with_services(): void
    {
        $other = Client::create([
            'id' => (string) Str::ulid(),
            'name' => 'Inny',
        ]);
        $cudzy = Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Cudzy',
            'owner_client_id' => $other->id,
        ]);

        $this->loginAs($this->client);

        $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $cudzy->id,
        ]))->assertNotFound();
    }

    private function makeService(array $overrides = []): BoardingService
    {
        return BoardingService::create(array_merge([
            'id' => (string) Str::ulid(),
            'name' => 'Usługa',
            'unit' => 'szt.',
            'frequency' => BoardingFrequency::Monthly->value,
            'price_cents' => 10000,
            'vat_rate' => '23',
            'is_active' => true,
        ], $overrides));
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
            'slug' => 'brd-'.$u,
            'name' => 'Boarding Stable',
            'db_name' => 'brd_'.$u,
            'db_username' => 'brd_'.substr($u, -8),
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

        Schema::connection('tenant')->create('boxes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 60);
            $t->string('label', 20)->nullable();
            $t->string('type', 32)->default('indoor');
            $t->unsignedSmallInteger('size_m2')->nullable();
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->unsignedInteger('monthly_rate_cents')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->string('microchip', 32)->nullable();
            $t->date('birth_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('box_assignments', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('box_id', 26);
            $t->timestamp('assigned_at');
            $t->timestamp('vacated_at')->nullable();
            $t->string('reason', 120)->nullable();
            $t->string('assigned_by_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
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
            $t->json('metadata')->nullable();
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
            $t->json('metadata')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horse_feeding_plan_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('meal', 16);
            $t->string('feed_type', 120);
            $t->decimal('amount_kg', 5, 2);
            $t->string('unit', 20)->default('kg');
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
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

        Schema::connection('tenant')->create('horse_documents', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('name', 200);
            $t->string('kind', 32);
            $t->string('description', 500)->nullable();
            $t->string('file_path', 500);
            $t->string('original_name', 255);
            $t->string('mime', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->string('uploaded_by_role', 16);
            $t->string('uploaded_by_user_id', 26)->nullable();
            $t->string('uploaded_by_client_id', 26)->nullable();
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
