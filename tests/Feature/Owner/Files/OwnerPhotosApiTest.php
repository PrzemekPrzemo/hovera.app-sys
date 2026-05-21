<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Files;

use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Faza 5 — Owner panel zdjęcia konia (cross-tenant). Service +
 * API tests w jednym pliku bo flow jest spójny (list ←→ upload ←→ delete
 * ←→ download).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class OwnerPhotosApiTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    private string $centralHorseId;

    private string $horseId;

    private string $clientId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_photos_').'.sqlite';
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

        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->centralHorseId = $registry->id;
        $this->horseId = $this->seedHorse($registry->id);
        $this->clientId = $this->seedClient($this->owner->id);
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/owner/horses/'.$this->centralHorseId.'/photos')
            ->assertUnauthorized();
    }

    public function test_index_returns_photos_from_both_uploaders(): void
    {
        $this->makeActiveBoarding();
        $this->seedPhoto('stable', null, 'iskra-trening.jpg');
        $this->seedPhoto('client', $this->clientId, 'iskra-z-padoku.jpg');

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/photos');

        $response->assertOk();
        $response->assertJsonPath('count', 2);
    }

    public function test_index_403_when_not_owner(): void
    {
        $other = User::create(['name' => 'X', 'email' => 'x-'.uniqid().'@e.t', 'password' => bcrypt('x')]);

        $this->actingAs($other)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/photos')
            ->assertForbidden();
    }

    public function test_upload_creates_photo_with_client_role(): void
    {
        $this->makeActiveBoarding();
        $file = UploadedFile::fake()->image('iskra.jpg');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/photos', [
                'file' => $file,
                'caption' => 'Po treningu',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.uploaded_by_role', 'client');
        $response->assertJsonPath('data.caption', 'Po treningu');
        $response->assertJsonPath('data.original_name', 'iskra.jpg');

        $this->assertSame(1, DB::connection('tenant')->table('horse_photos')->count());
    }

    public function test_upload_403_for_ended_boarding(): void
    {
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/photos', [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_upload_422_for_oversized_file(): void
    {
        $this->makeActiveBoarding();
        // 11 MB > 10 MB limit
        $big = UploadedFile::fake()->create('huge.jpg', 11 * 1024, 'image/jpeg');

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/photos', ['file' => $big])
            ->assertStatus(422);
    }

    public function test_upload_422_for_unsupported_mime(): void
    {
        $this->makeActiveBoarding();
        $pdf = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/photos', ['file' => $pdf])
            ->assertStatus(422);
    }

    public function test_delete_own_photo_soft_deletes(): void
    {
        $this->makeActiveBoarding();
        $photoId = $this->seedPhoto('client', $this->clientId, 'x.jpg');

        $this->actingAs($this->owner)
            ->deleteJson('/api/owner/photos/'.$this->stableTenant->id.'/'.$photoId)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull(DB::connection('tenant')->table('horse_photos')
            ->where('id', $photoId)->value('deleted_at'));
    }

    public function test_delete_403_when_trying_to_delete_stable_upload(): void
    {
        $this->makeActiveBoarding();
        $photoId = $this->seedPhoto('stable', null, 'stable-photo.jpg');

        $this->actingAs($this->owner)
            ->deleteJson('/api/owner/photos/'.$this->stableTenant->id.'/'.$photoId)
            ->assertForbidden();
    }

    public function test_delete_403_when_trying_to_delete_other_client_upload(): void
    {
        $this->makeActiveBoarding();
        // Inny client w tej stajni dodał zdjęcie — nasz owner nie może
        // go skasować.
        $otherClientId = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $otherClientId,
            'name' => 'Other Client',
            'central_user_id' => (string) Str::ulid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $photoId = $this->seedPhoto('client', $otherClientId, 'other.jpg');

        $this->actingAs($this->owner)
            ->deleteJson('/api/owner/photos/'.$this->stableTenant->id.'/'.$photoId)
            ->assertForbidden();
    }

    public function test_download_streams_file(): void
    {
        $this->makeActiveBoarding();

        // Upload by API żeby plik fizycznie był na fake disk.
        $upload = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/photos', [
                'file' => UploadedFile::fake()->image('iskra.jpg'),
            ]);
        $upload->assertCreated();
        $photoId = $upload->json('data.id');

        $response = $this->actingAs($this->owner)
            ->get('/api/owner/photos/'.$this->stableTenant->id.'/'.$photoId.'/download');

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_download_404_when_photo_belongs_to_someone_else(): void
    {
        $this->makeActiveBoarding();
        $other = User::create(['name' => 'Other', 'email' => 'o-'.uniqid().'@e.t', 'password' => bcrypt('x')]);
        $otherRegistry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $other->id,
            'name' => 'OtherHorse',
        ]);
        // Photo dla cudzego konia w tej samej stajni.
        $otherHorseId = $this->seedHorse($otherRegistry->id, 'Other');
        $photoId = (string) Str::ulid();
        DB::connection('tenant')->table('horse_photos')->insert([
            'id' => $photoId,
            'horse_id' => $otherHorseId,
            'file_path' => "horse-photos/{$this->stableTenant->id}/{$otherHorseId}/x.jpg",
            'original_name' => 'x.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'sort_order' => 0,
            'uploaded_by_role' => 'stable',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get('/api/owner/photos/'.$this->stableTenant->id.'/'.$photoId.'/download')
            ->assertNotFound();
    }

    public function test_download_404_for_unknown_photo(): void
    {
        $this->actingAs($this->owner)
            ->get('/api/owner/photos/'.$this->stableTenant->id.'/'.(string) Str::ulid().'/download')
            ->assertNotFound();
    }

    // ---- HELPERS ----

    private function makeActiveBoarding(): void
    {
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subMonths(3),
        ]);
    }

    private function seedHorse(string $centralHorseId, string $name = 'Iskra'): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert([
            'id' => $id,
            'central_horse_id' => $centralHorseId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedClient(string $centralUserId): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $id,
            'name' => 'Jan Owner',
            'central_user_id' => $centralUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedPhoto(string $role, ?string $clientId, string $originalName): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horse_photos')->insert([
            'id' => $id,
            'horse_id' => $this->horseId,
            'file_path' => "horse-photos/{$this->stableTenant->id}/{$this->horseId}/{$role}-{$id}_{$originalName}",
            'original_name' => $originalName,
            'mime' => 'image/jpeg',
            'size_bytes' => 100000,
            'caption' => null,
            'sort_order' => 0,
            'uploaded_by_role' => $role,
            'uploaded_by_user_id' => null,
            'uploaded_by_client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'photos-st-'.$u,
            'name' => 'Photos Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'photos_st_'.$u,
            'db_username' => 'photos_st_'.substr($u, -8),
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
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 24)->default('individual');
            $t->string('name', 200);
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('central_user_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
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
    }
}
