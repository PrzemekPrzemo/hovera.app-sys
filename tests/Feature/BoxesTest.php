<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\Box;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\Stable\BoxAssignmentService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_box_').'.sqlite';
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

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_box_is_vacant_when_no_horse_assigned(): void
    {
        $box = Box::create([
            'id' => (string) Str::ulid(),
            'name' => 'B-1',
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $this->assertTrue($box->isVacant());
        $this->assertSame(1, $box->freeSpots());
    }

    public function test_box_not_vacant_when_capacity_full(): void
    {
        $box = $this->makeBox('B-2');
        $client = $this->makeClient();
        Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Bucefał',
            'box_id' => $box->id,
            'owner_client_id' => $client->id,
        ]);

        $this->assertFalse($box->isVacant());
        $this->assertSame(0, $box->freeSpots());
    }

    public function test_box_inactive_is_never_vacant(): void
    {
        $box = Box::create([
            'id' => (string) Str::ulid(),
            'name' => 'Pod remontem',
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => false,
        ]);
        $this->assertFalse($box->isVacant());
        $this->assertSame(0, $box->freeSpots());
    }

    public function test_box_capacity_2_allows_two_horses(): void
    {
        $box = Box::create([
            'id' => (string) Str::ulid(),
            'name' => 'Box grupowy',
            'type' => 'indoor',
            'capacity' => 2,
            'is_active' => true,
        ]);
        $client = $this->makeClient();
        Horse::create(['id' => (string) Str::ulid(), 'name' => 'A', 'box_id' => $box->id, 'owner_client_id' => $client->id]);

        $this->assertSame(1, $box->fresh()->freeSpots());

        Horse::create(['id' => (string) Str::ulid(), 'name' => 'B', 'box_id' => $box->id, 'owner_client_id' => $client->id]);
        $this->assertSame(0, $box->fresh()->freeSpots());
    }

    public function test_assignment_service_atomically_moves_horse_between_boxes(): void
    {
        $box1 = $this->makeBox('B-1');
        $box2 = $this->makeBox('B-2');
        $client = $this->makeClient();
        $horse = Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Bucefał',
            'owner_client_id' => $client->id,
        ]);

        // Initial assignment
        app(BoxAssignmentService::class)->assign($horse, $box1, reason: 'pierwsza pensja');
        $horse->refresh();
        $this->assertSame($box1->id, $horse->box_id);
        $active = $horse->boxAssignments()->whereNull('vacated_at')->get();
        $this->assertCount(1, $active);
        $this->assertSame($box1->id, $active->first()->box_id);

        // Move to box 2
        app(BoxAssignmentService::class)->assign($horse, $box2, reason: 'remont B-1');
        $horse->refresh();
        $this->assertSame($box2->id, $horse->box_id);

        // Tylko 1 active
        $active = $horse->boxAssignments()->whereNull('vacated_at')->get();
        $this->assertCount(1, $active);
        $this->assertSame($box2->id, $active->first()->box_id);

        // Total history = 2 wpisy
        $this->assertSame(2, $horse->boxAssignments()->count());
    }

    public function test_assignment_service_no_op_when_same_box(): void
    {
        $box = $this->makeBox('B-1');
        $client = $this->makeClient();
        $horse = Horse::create(['id' => (string) Str::ulid(), 'name' => 'X', 'owner_client_id' => $client->id]);

        $first = app(BoxAssignmentService::class)->assign($horse, $box);
        $second = app(BoxAssignmentService::class)->assign($horse, $box);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $horse->boxAssignments()->count());
    }

    public function test_assignment_service_vacates_when_passed_null(): void
    {
        $box = $this->makeBox('B-1');
        $client = $this->makeClient();
        $horse = Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'X',
            'owner_client_id' => $client->id,
        ]);
        app(BoxAssignmentService::class)->assign($horse, $box);

        // Wypisz z boxa (np. wyjazd na zawody / sprzedaż)
        $result = app(BoxAssignmentService::class)->assign($horse, null, reason: 'sprzedaż');

        $this->assertNull($result);
        $horse->refresh();
        $this->assertNull($horse->box_id);
        $this->assertSame(0, $horse->boxAssignments()->whereNull('vacated_at')->count());
        $this->assertSame(1, $horse->boxAssignments()->count()); // wciąż jest w historii
    }

    public function test_observer_creates_assignment_when_horse_box_id_set_directly(): void
    {
        // Symulujemy zapis przez Filament resource form (bezpośrednia
        // zmiana box_id na Horse, nie przez BoxAssignmentService).
        $box = $this->makeBox('B-1');
        $client = $this->makeClient();
        $horse = Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'X',
            'owner_client_id' => $client->id,
            'box_id' => $box->id,
        ]);

        $assignments = $horse->boxAssignments;
        $this->assertCount(1, $assignments);
        $this->assertSame($box->id, $assignments->first()->box_id);
        $this->assertNull($assignments->first()->vacated_at);
    }

    public function test_observer_closes_old_and_opens_new_on_box_change(): void
    {
        $box1 = $this->makeBox('B-1');
        $box2 = $this->makeBox('B-2');
        $client = $this->makeClient();
        $horse = Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'X',
            'owner_client_id' => $client->id,
            'box_id' => $box1->id,
        ]);

        $horse->forceFill(['box_id' => $box2->id])->save();

        $horse->refresh();
        $assignments = $horse->boxAssignments;
        $this->assertCount(2, $assignments);

        $active = $assignments->whereNull('vacated_at');
        $this->assertCount(1, $active);
        $this->assertSame($box2->id, $active->first()->box_id);
    }

    public function test_public_site_shows_box_availability_widget(): void
    {
        // Setup: 3 boxy aktywne, 1 zajęty, 1 nieaktywny (nie liczy się)
        $box1 = $this->makeBox('B-1');
        $box2 = $this->makeBox('B-2');
        $box3 = $this->makeBox('B-3');
        Box::create([
            'id' => (string) Str::ulid(),
            'name' => 'B-4 (remont)',
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => false,
        ]);
        $client = $this->makeClient();
        Horse::create(['id' => (string) Str::ulid(), 'name' => 'Bucefał', 'box_id' => $box1->id, 'owner_client_id' => $client->id]);

        $response = $this->get('/s/'.$this->tenant->slug);

        $response->assertOk();
        $content = (string) $response->getContent();
        // 3 aktywne boksy, 1 zajęty → 2 wolne
        $this->assertStringContainsString('Mamy 2', $content);
        $this->assertStringContainsString('na 3 łącznie', $content);
    }

    public function test_public_site_shows_full_message_when_all_occupied(): void
    {
        $box = $this->makeBox('B-1');
        $client = $this->makeClient();
        Horse::create(['id' => (string) Str::ulid(), 'name' => 'X', 'box_id' => $box->id, 'owner_client_id' => $client->id]);

        $response = $this->get('/s/'.$this->tenant->slug);

        $response->assertOk()->assertSee('Wszystkie boksy są zajęte');
    }

    public function test_public_site_widget_hidden_when_no_boxes_configured(): void
    {
        // Stajnia bez boxów → widget się nie pokazuje (widok nie crashuje)
        $response = $this->get('/s/'.$this->tenant->slug);

        $response->assertOk()
            ->assertDontSee('wolne boksy')
            ->assertDontSee('Wszystkie boksy są zajęte');
    }

    public function test_public_site_widget_can_be_disabled_in_settings(): void
    {
        $box = $this->makeBox('B-1');

        $this->tenant->forceFill([
            'settings' => ['public_profile' => ['show_box_availability' => false]],
        ])->save();
        \Cache::forget('public_box_availability:'.$this->tenant->slug);

        $response = $this->get('/s/'.$this->tenant->slug);

        $response->assertOk()->assertDontSee('wolne boksy');
    }

    private function makeBox(string $name): Box
    {
        return Box::create([
            'id' => (string) Str::ulid(),
            'name' => $name,
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => true,
        ]);
    }

    private function makeClient(): Client
    {
        return Client::create([
            'id' => (string) Str::ulid(),
            'name' => 'Klient',
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'box-'.$u,
            'name' => 'Box Stable',
            'db_name' => 'box_'.$u,
            'db_username' => 'box_'.substr($u, -8),
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
            $t->string('passport_number', 64)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 32)->nullable();
            $t->string('color', 60)->nullable();
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
    }
}
