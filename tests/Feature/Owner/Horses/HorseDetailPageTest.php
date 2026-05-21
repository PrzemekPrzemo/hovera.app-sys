<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Horses;

use App\Enums\TenantType;
use App\Filament\Owner\Pages\HorseDetail;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Pokrywa Filament Page `HorseDetail` — owner widzi pełne dane konia
 * w stajni gdy ma active boarding. Faza 1 Owner ↔ Stable shared view.
 *
 * Test ładuje page directly (bez przechodzenia przez panel http routing)
 * i sprawdza że mount() prawidłowo gate'uje + ładuje snapshot.
 */
class HorseDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_pgdetail_').'.sqlite';
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

        // Mock TenantManager żeby execute() w snapshot service nie próbował
        // konfigurować connection (zostałby nadpisany SQLite → MySQL).
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

        // Owner panel używa filament.owner.* routes — getUrl wymaga
        // setCurrentPanel (analogicznie do testów Calculator save-inline).
        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_mount_loads_full_snapshot_for_active_boarding(): void
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->makeActiveBoarding($registry);
        $this->seedHorseInStable($registry->id, [
            'name' => 'Iskra',
            'breed' => 'KWPN',
            'sex' => 'mare',
            'color' => 'gniada',
        ]);

        $this->actingAs($this->owner);

        $page = new HorseDetail;
        $page->mount($registry->id);

        $this->assertNotNull($page->snapshot);
        $this->assertSame('Iskra', $page->snapshot->name);
        $this->assertSame('KWPN', $page->snapshot->breed);
        $this->assertSame($this->stableTenant->id, $page->stableTenant->id);
        $this->assertNotNull($page->assignment);
        $this->assertSame(HorseBoardingAssignment::STATUS_ACTIVE, $page->assignment->status);
    }

    public function test_mount_aborts_403_when_not_primary_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $other->id, // cudzy koń
            'name' => 'Iskra',
        ]);
        $this->actingAs($this->owner);

        $page = new HorseDetail;
        $this->assertHttpException(403, fn () => $page->mount($registry->id));
    }

    public function test_mount_aborts_403_when_only_pending_boarding(): void
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);

        $this->actingAs($this->owner);
        $page = new HorseDetail;
        $this->assertHttpException(403, fn () => $page->mount($registry->id));
    }

    public function test_mount_aborts_404_on_sync_rift_horse_missing_in_stable(): void
    {
        // Assignment + registry istnieją centralnie, ale w stable DB
        // nie ma horse z tym central_horse_id (rzadki przypadek, ale
        // page powinien gracefully 404 z PL komunikatem).
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->makeActiveBoarding($registry);
        // NIE seedujemy horse w stable.

        $this->actingAs($this->owner);
        $page = new HorseDetail;
        $this->assertHttpException(404, fn () => $page->mount($registry->id));
    }

    /**
     * Helper — sprawdza że callable rzuca HttpException z określonym
     * HTTP status code (`getStatusCode()`, nie `getCode()` które dla
     * Laravel'a jest zawsze 0).
     */
    private function assertHttpException(int $expectedStatus, callable $fn): void
    {
        try {
            $fn();
        } catch (HttpException $e) {
            $this->assertSame($expectedStatus, $e->getStatusCode());

            return;
        }
        $this->fail("Expected HttpException with status {$expectedStatus}, none thrown");
    }

    public function test_format_cents_helper_handles_null(): void
    {
        $page = new HorseDetail;

        $this->assertSame('—', $page->formatCents(null));
        $this->assertSame('1 234,56 PLN', $page->formatCents(123456));
        $this->assertSame('0,00 EUR', $page->formatCents(0, 'EUR'));
    }

    public function test_title_falls_back_when_snapshot_missing(): void
    {
        $page = new HorseDetail;
        // snapshot nie ustawiony → fallback z translation key
        $this->assertSame(
            __('owner/horse_detail.title.fallback'),
            $page->getTitle()
        );
    }

    public function test_title_uses_horse_name_after_mount(): void
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->makeActiveBoarding($registry);
        $this->seedHorseInStable($registry->id, ['name' => 'Iskra']);

        $this->actingAs($this->owner);
        $page = new HorseDetail;
        $page->mount($registry->id);

        $this->assertSame('Iskra', $page->getTitle());
    }

    private function makeActiveBoarding(CentralHorseRegistry $registry): HorseBoardingAssignment
    {
        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subDays(30),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedHorseInStable(string $centralHorseId, array $overrides = []): void
    {
        DB::connection('tenant')->table('horses')->insert(array_merge([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $centralHorseId,
            'name' => 'Horse',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'page-st-'.$u,
            'name' => 'Page Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'page_st_'.$u,
            'db_username' => 'page_st_'.substr($u, -8),
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
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 32)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 24)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->string('cover_image_path', 500)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->string('livejumping_profile_url', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        // Pages test nie testuje box/services szczegółowo, ale snapshot
        // service ich requires (eager load) — minimal stub tables żeby
        // query nie crashował na brakujących relacjach.
        Schema::connection('tenant')->create('boxes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('building_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('type', 32)->default('indoor');
            $t->integer('capacity')->default(1);
            $t->integer('monthly_rate_cents')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('buildings', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('box_assignments', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('box_id', 26);
            $t->string('assigned_by_user_id', 26)->nullable();
            $t->timestamp('assigned_at');
            $t->timestamp('vacated_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
        Schema::connection('tenant')->create('boarding_services', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->string('unit', 32);
            $t->string('frequency', 16);
            $t->integer('price_cents');
            $t->string('vat_rate', 8)->default('23');
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('horse_boarding_services', function ($t) {
            $t->string('horse_id', 26);
            $t->string('boarding_service_id', 26);
            $t->integer('price_override_cents')->nullable();
            $t->decimal('quantity', 10, 2)->default(1);
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->primary(['horse_id', 'boarding_service_id']);
        });
    }
}
