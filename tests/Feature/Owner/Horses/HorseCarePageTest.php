<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Horses;

use App\Enums\TenantType;
use App\Filament\Owner\Pages\HorseCare;
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
 * Pokrywa owner page `HorseCare` — waga + plan żywienia. Sprawdza
 * cross-tenant snapshot loading (TenantManager::execute mock) + delta
 * computation + access gate.
 *
 * Pattern z HorseDetailPageTest — SQLite tenant DB, mock TenantManager,
 * Filament panel set żeby getUrl resolwało route.
 */
class HorseCarePageTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_pgcare_').'.sqlite';
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
            'email' => 'jan-care-'.uniqid().'@example.test',
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

        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_mount_loads_weights_and_feeding_plan(): void
    {
        $registry = $this->makeRegistry();
        $this->makeActiveBoarding($registry);
        $stableHorseId = $this->seedHorseInStable($registry->id);

        // Trzy pomiary w odstępach miesiąca — delta liczona w service.
        $this->seedWeight($stableHorseId, '2026-01-15', 520.0);
        $this->seedWeight($stableHorseId, '2026-02-15', 523.5);  // +3.5
        $this->seedWeight($stableHorseId, '2026-03-15', 518.0);  // -5.5

        $this->seedFeedingItem($stableHorseId, 'breakfast', 'Owies', 2.5, 1);
        $this->seedFeedingItem($stableHorseId, 'evening', 'Siano', 5.0, 2);
        $this->seedFeedingItem($stableHorseId, 'midday', 'Inactive', 1.0, 0, isActive: false);

        $this->actingAs($this->owner);

        $page = new HorseCare;
        $page->mount($registry->id);

        $this->assertCount(3, $page->weights);
        $this->assertSame(520.0, $page->weights[0]->weightKg);
        $this->assertNull($page->weights[0]->deltaKg);
        $this->assertEqualsWithDelta(3.5, $page->weights[1]->deltaKg, 0.01);
        $this->assertEqualsWithDelta(-5.5, $page->weights[2]->deltaKg, 0.01);

        // Plan żywienia — tylko active (2 z 3 seeded). Sort wg modelowego scope.
        $this->assertCount(2, $page->feedingPlan);
        $feedTypes = array_map(fn ($i) => $i->feedType, $page->feedingPlan);
        $this->assertContains('Owies', $feedTypes);
        $this->assertContains('Siano', $feedTypes);
        $this->assertNotContains('Inactive', $feedTypes);
    }

    public function test_latest_weight_returns_most_recent(): void
    {
        $registry = $this->makeRegistry();
        $this->makeActiveBoarding($registry);
        $stableHorseId = $this->seedHorseInStable($registry->id);

        $this->seedWeight($stableHorseId, '2026-01-15', 520.0);
        $this->seedWeight($stableHorseId, '2026-03-15', 518.0);
        $this->seedWeight($stableHorseId, '2026-02-15', 523.5);

        $this->actingAs($this->owner);
        $page = new HorseCare;
        $page->mount($registry->id);

        $latest = $page->latestWeight();
        $this->assertNotNull($latest);
        $this->assertSame(518.0, $latest->weightKg);
        $this->assertSame('2026-03-15', $latest->measuredAt->format('Y-m-d'));
    }

    public function test_mount_aborts_403_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $other->id,
            'name' => 'Iskra',
        ]);

        $this->actingAs($this->owner);
        $page = new HorseCare;
        $this->assertHttpException(403, fn () => $page->mount($registry->id));
    }

    public function test_mount_allows_ended_boarding_readonly(): void
    {
        $registry = $this->makeRegistry();
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subMonth(),
        ]);
        $this->seedHorseInStable($registry->id);

        $this->actingAs($this->owner);
        $page = new HorseCare;
        $page->mount($registry->id);

        $this->assertNotNull($page->assignment);
        $this->assertSame(HorseBoardingAssignment::STATUS_ENDED, $page->assignment->status);
    }

    public function test_format_delta_helpers(): void
    {
        $page = new HorseCare;
        $this->assertSame('—', $page->formatDelta(null));
        $this->assertSame('+3,5 kg', $page->formatDelta(3.5));
        $this->assertSame('-5,5 kg', $page->formatDelta(-5.5));
        $this->assertSame('text-gray-500', $page->deltaColorClass(null));
        $this->assertSame('text-gray-500', $page->deltaColorClass(2.0));   // <5 = noise
        $this->assertSame('text-emerald-600', $page->deltaColorClass(7.0));
        $this->assertSame('text-amber-600', $page->deltaColorClass(-7.0));
    }

    private function assertHttpException(int $expectedStatus, callable $fn): void
    {
        try {
            $fn();
        } catch (HttpException $e) {
            $this->assertSame($expectedStatus, $e->getStatusCode());

            return;
        }
        $this->fail("Expected HttpException with status {$expectedStatus}");
    }

    private function makeRegistry(): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
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

    private function seedHorseInStable(string $centralHorseId): string
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

    private function seedWeight(string $horseId, string $measuredAt, float $kg): void
    {
        DB::connection('tenant')->table('horse_weight_measurements')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $horseId,
            'measured_at' => $measuredAt,
            'weight_kg' => $kg,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedFeedingItem(string $horseId, string $meal, string $feedType, float $amount, int $sortOrder, bool $isActive = true): void
    {
        DB::connection('tenant')->table('horse_feeding_plan_items')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $horseId,
            'meal' => $meal,
            'feed_type' => $feedType,
            'amount_kg' => $amount,
            'unit' => 'kg',
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'page-care-'.$u,
            'name' => 'Care Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'page_care_'.$u,
            'db_username' => 'page_care_'.substr($u, -8),
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

        Schema::connection('tenant')->create('horse_weight_measurements', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->date('measured_at');
            $t->decimal('weight_kg', 5, 1);
            $t->decimal('girth_cm', 5, 1)->nullable();
            $t->text('notes')->nullable();
            $t->string('measured_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
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
    }
}
