<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Timeline;

use App\Domain\Horses\Timeline\HorseTimelineEntry;
use App\Domain\Horses\Timeline\HorseTimelineFilter;
use App\Domain\Horses\Timeline\HorseTimelineService;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa HorseTimelineService — cross-tenant aggregator eventów z 6
 * źródeł (health, box, weight, activity, photo, document) zmergowanych
 * w jeden DESC feed z filtrami.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 2".
 */
class HorseTimelineServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private string $centralHorseId;

    private string $horseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_timeline_').'.sqlite';
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
        $this->centralHorseId = (string) Str::ulid();
        $this->horseId = $this->seedHorse($this->centralHorseId);

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
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_returns_empty_when_horse_not_in_stable(): void
    {
        $result = app(HorseTimelineService::class)
            ->forHorse('01HZZZZZZZZZZZZZZZZZZZZZ', $this->stableTenant);

        $this->assertSame([], $result);
    }

    public function test_collects_health_records(): void
    {
        $this->seedHealthRecord('vet_visit', '2026-05-15 10:00', 'Wizyta kontrolna', 15000);
        $this->seedHealthRecord('vaccination', '2026-04-10 09:00', 'Tetanus');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(2, $entries);
        $this->assertSame(HorseTimelineEntry::KIND_HEALTH, $entries[0]->kind);
        $this->assertSame('vet_visit', $entries[0]->subkind);
        $this->assertSame('Wizyta kontrolna', $entries[0]->title);
        $this->assertSame(15000, $entries[0]->costCents);
    }

    public function test_collects_box_assignments_assigned_and_vacated(): void
    {
        $boxId = $this->seedBox('Box A', 'Stajnia główna');
        $this->seedBoxAssignment($boxId, '2026-01-15 08:00', '2026-04-01 09:00');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        // 1 box generuje 2 entries: assigned + vacated
        $this->assertCount(2, $entries);
        $boxEntries = array_values(array_filter($entries, fn ($e) => $e->kind === 'box'));
        $subkinds = array_map(fn ($e) => $e->subkind, $boxEntries);
        $this->assertContains('assigned', $subkinds);
        $this->assertContains('vacated', $subkinds);

        $vacated = array_values(array_filter($boxEntries, fn ($e) => $e->subkind === 'vacated'))[0];
        $this->assertSame('Box A', $vacated->title);
        $this->assertSame('Stajnia główna', $vacated->description);
    }

    public function test_active_box_assignment_only_emits_assigned_event(): void
    {
        $boxId = $this->seedBox('Box B');
        $this->seedBoxAssignment($boxId, '2026-05-01 10:00', null);

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(1, $entries);
        $this->assertSame('assigned', $entries[0]->subkind);
    }

    public function test_collects_weight_measurements(): void
    {
        $this->seedWeight('2026-05-01', 575.5, 'Po treningu');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(1, $entries);
        $this->assertSame('weight', $entries[0]->kind);
        $this->assertStringContainsString('575.5', $entries[0]->title);
        $this->assertSame(575.5, $entries[0]->payload['weight_kg']);
    }

    public function test_collects_stable_activities(): void
    {
        $this->seedActivity('exercise', '2026-05-10 16:00', 'Lonża 20 min');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(1, $entries);
        $this->assertSame('activity', $entries[0]->kind);
        $this->assertSame('exercise', $entries[0]->subkind);
        $this->assertSame('Lonża 20 min', $entries[0]->title);
    }

    public function test_collects_photos_with_actor_role_owner_when_uploaded_by_client(): void
    {
        $this->seedPhoto('uploaded_by_role', 'client', '2026-05-05 12:00');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(1, $entries);
        $this->assertSame('photo', $entries[0]->kind);
        $this->assertSame(HorseTimelineEntry::ACTOR_OWNER, $entries[0]->actorRole);
    }

    public function test_collects_documents_with_subkind_from_enum(): void
    {
        $this->seedDocument('passport', 'Paszport konia', '2026-03-01 12:00');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(1, $entries);
        $this->assertSame('document', $entries[0]->kind);
        $this->assertSame('passport', $entries[0]->subkind);
        $this->assertSame('Paszport konia', $entries[0]->title);
    }

    public function test_entries_sorted_desc_by_occurred_at(): void
    {
        $this->seedHealthRecord('vaccination', '2026-01-10 10:00', 'Old');
        $this->seedHealthRecord('vet_visit', '2026-05-15 10:00', 'Recent');
        $this->seedWeight('2026-03-20', 580);

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        $this->assertCount(3, $entries);
        // Najnowszy pierwszy (Recent z maja)
        $this->assertSame('Recent', $entries[0]->title);
        // Środek (Weight z marca)
        $this->assertSame('weight', $entries[1]->kind);
        // Najstarszy (Old ze stycznia)
        $this->assertSame('Old', $entries[2]->title);
    }

    public function test_filter_kinds_restricts_to_selected_categories(): void
    {
        $this->seedHealthRecord('vet_visit', '2026-05-15 10:00', 'Vet');
        $this->seedWeight('2026-05-10', 580);
        $this->seedActivity('exercise', '2026-05-12 16:00', 'Lonża');

        $filter = new HorseTimelineFilter(kinds: ['health', 'activity']);
        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant, $filter);

        // 2 entries (health + activity), weight pominięte
        $this->assertCount(2, $entries);
        $kinds = array_unique(array_map(fn ($e) => $e->kind, $entries));
        $this->assertContains('health', $kinds);
        $this->assertContains('activity', $kinds);
        $this->assertNotContains('weight', $kinds);
    }

    public function test_filter_date_range_excludes_outside_events(): void
    {
        $this->seedHealthRecord('vet_visit', '2026-01-10 10:00', 'January');
        $this->seedHealthRecord('vet_visit', '2026-05-15 10:00', 'May');
        $this->seedHealthRecord('vet_visit', '2026-09-20 10:00', 'September');

        $filter = new HorseTimelineFilter(
            from: Carbon::parse('2026-04-01'),
            to: Carbon::parse('2026-07-31 23:59:59'),
        );
        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant, $filter);

        $this->assertCount(1, $entries);
        $this->assertSame('May', $entries[0]->title);
    }

    public function test_filter_limit_caps_result_size(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedWeight("2026-05-0{$i}", 570 + $i);
        }

        $filter = new HorseTimelineFilter(limit: 3);
        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant, $filter);

        $this->assertCount(3, $entries);
    }

    public function test_merges_all_six_sources_in_single_feed(): void
    {
        $boxId = $this->seedBox('Box X');
        $this->seedBoxAssignment($boxId, '2026-05-01 08:00', null);
        $this->seedHealthRecord('vet_visit', '2026-05-02 10:00', 'Vet');
        $this->seedWeight('2026-05-03', 580);
        $this->seedActivity('exercise', '2026-05-04 16:00', 'Lonża');
        $this->seedPhoto('uploaded_by_role', 'stable', '2026-05-05 12:00');
        $this->seedDocument('contract', 'Kontrakt', '2026-05-06 14:00');

        $entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant);

        // 6 events, każdy z innego źródła
        $this->assertCount(6, $entries);
        $kinds = array_map(fn ($e) => $e->kind, $entries);
        $this->assertEqualsCanonicalizing(
            ['box', 'health', 'weight', 'activity', 'photo', 'document'],
            $kinds,
        );
    }

    // ---- HELPERS ----

    private function seedHorse(string $centralHorseId): string
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

    private function seedBox(string $name, ?string $building = null): string
    {
        $buildingId = null;
        if ($building !== null) {
            $buildingId = (string) Str::ulid();
            DB::connection('tenant')->table('buildings')->insert([
                'id' => $buildingId,
                'name' => $building,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $boxId = (string) Str::ulid();
        DB::connection('tenant')->table('boxes')->insert([
            'id' => $boxId,
            'building_id' => $buildingId,
            'name' => $name,
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $boxId;
    }

    private function seedBoxAssignment(string $boxId, string $assignedAt, ?string $vacatedAt): void
    {
        DB::connection('tenant')->table('box_assignments')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horseId,
            'box_id' => $boxId,
            'assigned_at' => Carbon::parse($assignedAt),
            'vacated_at' => $vacatedAt !== null ? Carbon::parse($vacatedAt) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedHealthRecord(string $type, string $performedAt, string $summary, ?int $costCents = null): void
    {
        DB::connection('tenant')->table('health_records')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horseId,
            'type' => $type,
            'performed_at' => Carbon::parse($performedAt),
            'summary' => $summary,
            'cost_cents' => $costCents,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedWeight(string $measuredAt, float $weightKg, ?string $notes = null): void
    {
        DB::connection('tenant')->table('horse_weight_measurements')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horseId,
            'measured_at' => $measuredAt,
            'weight_kg' => $weightKg,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedActivity(string $type, string $performedAt, string $summary): void
    {
        DB::connection('tenant')->table('stable_activities')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horseId,
            'type' => $type,
            'performed_at' => Carbon::parse($performedAt),
            'summary' => $summary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPhoto(string $key, string $role, string $createdAt): void
    {
        DB::connection('tenant')->table('horse_photos')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horseId,
            'file_path' => 'tmp/photo.jpg',
            'original_name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 100000,
            'caption' => 'Po treningu',
            'sort_order' => 0,
            $key => $role,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }

    private function seedDocument(string $kind, string $name, string $createdAt): void
    {
        DB::connection('tenant')->table('horse_documents')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horseId,
            'name' => $name,
            'kind' => $kind,
            'file_path' => 'tmp/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 200000,
            'uploaded_by_role' => 'stable',
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'tl-st-'.$u,
            'name' => 'Timeline Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'tl_st_'.$u,
            'db_username' => 'tl_st_'.substr($u, -8),
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
        Schema::connection('tenant')->create('buildings', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
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
        Schema::connection('tenant')->create('box_assignments', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('box_id', 26);
            $t->string('assigned_by_user_id', 26)->nullable();
            $t->timestamp('assigned_at');
            $t->timestamp('vacated_at')->nullable();
            $t->string('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->timestamp('performed_at');
            $t->string('performed_by')->nullable();
            $t->string('specialist_id', 26)->nullable();
            $t->string('summary')->nullable();
            $t->text('details')->nullable();
            $t->date('next_due_at')->nullable();
            $t->integer('cost_cents')->nullable();
            $t->json('attachments')->nullable();
            $t->json('metadata')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('horse_weight_measurements', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->date('measured_at');
            $t->decimal('weight_kg', 6, 1);
            $t->decimal('girth_cm', 5, 1)->nullable();
            $t->text('notes')->nullable();
            $t->string('measured_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
        Schema::connection('tenant')->create('stable_activities', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->timestamp('performed_at');
            $t->string('performed_by')->nullable();
            $t->string('specialist_id', 26)->nullable();
            $t->string('summary')->nullable();
            $t->text('details')->nullable();
            $t->integer('cost_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('horse_photos', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('file_path', 500);
            $t->string('original_name', 255)->nullable();
            $t->string('mime', 100)->nullable();
            $t->integer('size_bytes')->nullable();
            $t->string('caption', 500)->nullable();
            $t->integer('sort_order')->default(0);
            $t->string('uploaded_by_role', 16)->nullable();
            $t->string('uploaded_by_user_id', 26)->nullable();
            $t->string('uploaded_by_client_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::connection('tenant')->create('horse_documents', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('name');
            $t->string('kind', 32);
            $t->text('description')->nullable();
            $t->string('file_path', 500);
            $t->string('original_name', 255)->nullable();
            $t->string('mime', 100)->nullable();
            $t->integer('size_bytes')->nullable();
            $t->string('uploaded_by_role', 16)->nullable();
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
