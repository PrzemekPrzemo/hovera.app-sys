<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Files;

use App\Domain\Files\Owner\Snapshots\HorsePhotoSnapshot;
use App\Enums\TenantType;
use App\Filament\Owner\Pages\HorseGallery;
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
 * Pokrywa Filament Page HorseGallery — mount + access gate + load
 * photos. Upload/delete flow są pokryte w OwnerPhotosApiTest (service
 * level); tutaj sprawdzamy że Page wire'uje się correctly.
 */
class HorseGalleryPageTest extends TestCase
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

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_gallery_').'.sqlite';
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

        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_mount_with_active_loads_photos_and_enables_upload(): void
    {
        $this->makeActiveBoarding();
        $this->seedPhoto('stable', 'stable-1.jpg');
        $this->seedPhoto('client', 'client-1.jpg');

        $this->actingAs($this->owner);
        $page = new HorseGallery;
        $page->mount($this->centralHorseId);

        $this->assertCount(2, $page->photos);
        $this->assertTrue($page->canUpload);
        $this->assertSame($this->stableTenant->id, $page->stableTenant->id);
    }

    public function test_mount_with_ended_allows_read_but_disables_upload(): void
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
        $this->seedPhoto('stable', 'old.jpg');

        $this->actingAs($this->owner);
        $page = new HorseGallery;
        $page->mount($this->centralHorseId);

        $this->assertCount(1, $page->photos);
        $this->assertFalse($page->canUpload);
    }

    public function test_mount_aborts_403_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $this->actingAs($other);
        $page = new HorseGallery;
        try {
            $page->mount($this->centralHorseId);
            $this->fail('Expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_mount_aborts_403_when_no_assignments(): void
    {
        $this->actingAs($this->owner);
        $page = new HorseGallery;
        try {
            $page->mount($this->centralHorseId);
            $this->fail('Expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_can_delete_helper(): void
    {
        $this->makeActiveBoarding();
        $page = new HorseGallery;
        $page->canUpload = true;

        // Owner's own photo (client) — can delete gdy active
        $ownPhoto = $this->makeSnapshot('client');
        $this->assertTrue($page->canDelete($ownPhoto));

        // Stable photo — never can delete (chronione)
        $stablePhoto = $this->makeSnapshot('stable');
        $this->assertFalse($page->canDelete($stablePhoto));

        // Ended boarding (canUpload=false) — nie można nawet swoich
        $page->canUpload = false;
        $this->assertFalse($page->canDelete($ownPhoto));
    }

    public function test_download_url_includes_tenant_and_photo_id(): void
    {
        $this->makeActiveBoarding();
        $this->actingAs($this->owner);
        $page = new HorseGallery;
        $page->mount($this->centralHorseId);

        $photo = $this->makeSnapshot('client', 'photo-x-id');
        $url = $page->downloadUrl($photo);

        $this->assertStringContainsString('/api/owner/photos/', $url);
        $this->assertStringContainsString($page->stableTenant->id, $url);
        $this->assertStringContainsString('photo-x-id', $url);
    }

    public function test_format_file_size_outputs_units(): void
    {
        $page = new HorseGallery;
        $this->assertSame('500 B', $page->formatFileSize(500));
        $this->assertSame('1,5 KB', $page->formatFileSize(1536));
        $this->assertSame('2,5 MB', $page->formatFileSize(2_621_440));
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

    private function seedPhoto(string $role, string $name): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horse_photos')->insert([
            'id' => $id,
            'horse_id' => $this->horseId,
            'file_path' => "horse-photos/{$this->stableTenant->id}/{$this->horseId}/{$role}-{$id}_{$name}",
            'original_name' => $name,
            'mime' => 'image/jpeg',
            'size_bytes' => 100000,
            'caption' => null,
            'sort_order' => 0,
            'uploaded_by_role' => $role,
            'uploaded_by_user_id' => null,
            'uploaded_by_client_id' => $role === 'client' ? $this->clientId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeSnapshot(string $role, ?string $id = null): HorsePhotoSnapshot
    {
        return new HorsePhotoSnapshot(
            id: $id ?? (string) Str::ulid(),
            stableTenantId: $this->stableTenant->id,
            originalName: 'x.jpg',
            caption: null,
            mime: 'image/jpeg',
            sizeBytes: 100,
            sortOrder: 0,
            uploadedByRole: $role,
            uploaderName: null,
            createdAt: now(),
        );
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'gal-st-'.$u,
            'name' => 'Gallery Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'gal_st_'.$u,
            'db_username' => 'gal_st_'.substr($u, -8),
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
