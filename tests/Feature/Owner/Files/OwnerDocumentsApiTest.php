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
 * Pokrywa Faza 5 Documents — service + API tests w jednym pliku.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class OwnerDocumentsApiTest extends TestCase
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

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_docs_').'.sqlite';
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
        $this->getJson('/api/owner/horses/'.$this->centralHorseId.'/documents')
            ->assertUnauthorized();
    }

    public function test_index_returns_documents_from_both_uploaders(): void
    {
        $this->makeActiveBoarding();
        $this->seedDocument('stable', 'passport', 'Paszport konia', null);
        $this->seedDocument('client', 'contract', 'Kontrakt boardingu', $this->clientId);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/documents');

        $response->assertOk();
        $response->assertJsonPath('count', 2);
    }

    public function test_index_403_when_not_owner(): void
    {
        $other = User::create(['name' => 'X', 'email' => 'x-'.uniqid().'@e.t', 'password' => bcrypt('x')]);

        $this->actingAs($other)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/documents')
            ->assertForbidden();
    }

    public function test_upload_creates_document_with_kind_and_valid_until(): void
    {
        $this->makeActiveBoarding();
        $file = UploadedFile::fake()->create('passport.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => $file,
                'name' => 'Paszport Iskry',
                'kind' => 'passport',
                'description' => 'Aktualny paszport',
                'valid_until' => '2030-12-31',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Paszport Iskry');
        $response->assertJsonPath('data.kind', 'passport');
        $response->assertJsonPath('data.uploaded_by_role', 'client');
        $response->assertJsonPath('data.valid_until', '2030-12-31');

        $this->assertSame(1, DB::connection('tenant')->table('horse_documents')->count());
    }

    public function test_upload_422_for_invalid_kind(): void
    {
        $this->makeActiveBoarding();

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
                'name' => 'X',
                'kind' => 'nonexistent_kind_value',
            ])
            ->assertStatus(422);
    }

    public function test_upload_422_for_oversized_file(): void
    {
        $this->makeActiveBoarding();
        // 26 MB > 25 MB limit
        $big = UploadedFile::fake()->create('huge.pdf', 26 * 1024, 'application/pdf');

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => $big,
                'name' => 'Big',
                'kind' => 'other',
            ])
            ->assertStatus(422);
    }

    public function test_upload_422_for_unsupported_mime(): void
    {
        $this->makeActiveBoarding();
        $exe = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => $exe,
                'name' => 'X',
                'kind' => 'other',
            ])
            ->assertStatus(422);
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
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
                'name' => 'X',
                'kind' => 'other',
            ])
            ->assertForbidden();
    }

    public function test_upload_validates_valid_until_after_valid_from(): void
    {
        $this->makeActiveBoarding();

        $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
                'name' => 'X',
                'kind' => 'other',
                'valid_from' => '2026-12-31',
                'valid_until' => '2026-01-01', // before valid_from
            ])
            ->assertStatus(422);
    }

    public function test_delete_own_document_soft_deletes(): void
    {
        $this->makeActiveBoarding();
        $docId = $this->seedDocument('client', 'passport', 'My passport', $this->clientId);

        $this->actingAs($this->owner)
            ->deleteJson('/api/owner/documents/'.$this->stableTenant->id.'/'.$docId)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull(DB::connection('tenant')->table('horse_documents')
            ->where('id', $docId)->value('deleted_at'));
    }

    public function test_delete_403_when_trying_to_delete_stable_upload(): void
    {
        $this->makeActiveBoarding();
        $docId = $this->seedDocument('stable', 'contract', 'Stable contract', null);

        $this->actingAs($this->owner)
            ->deleteJson('/api/owner/documents/'.$this->stableTenant->id.'/'.$docId)
            ->assertForbidden();
    }

    public function test_download_streams_file(): void
    {
        $this->makeActiveBoarding();
        $upload = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/documents', [
                'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
                'name' => 'Passport',
                'kind' => 'passport',
            ]);
        $upload->assertCreated();
        $docId = $upload->json('data.id');

        $response = $this->actingAs($this->owner)
            ->get('/api/owner/documents/'.$this->stableTenant->id.'/'.$docId.'/download');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_download_404_for_other_owners_document(): void
    {
        $this->makeActiveBoarding();
        $other = User::create(['name' => 'Other', 'email' => 'o-'.uniqid().'@e.t', 'password' => bcrypt('x')]);
        $otherRegistry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $other->id,
            'name' => 'Other',
        ]);
        $otherHorseId = $this->seedHorse($otherRegistry->id, 'Other');
        $docId = (string) Str::ulid();
        DB::connection('tenant')->table('horse_documents')->insert([
            'id' => $docId,
            'horse_id' => $otherHorseId,
            'name' => 'X',
            'kind' => 'other',
            'file_path' => "horse-documents/{$this->stableTenant->id}/{$otherHorseId}/x.pdf",
            'original_name' => 'x.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_by_role' => 'stable',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get('/api/owner/documents/'.$this->stableTenant->id.'/'.$docId.'/download')
            ->assertNotFound();
    }

    public function test_is_expired_helper_via_dto(): void
    {
        $this->makeActiveBoarding();
        $this->seedDocument('stable', 'passport', 'Old passport', null, '2020-01-01');

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/documents');

        $response->assertOk();
        $response->assertJsonPath('data.0.is_expired', true);
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

    private function seedDocument(string $role, string $kind, string $name, ?string $clientId, ?string $validUntil = null): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horse_documents')->insert([
            'id' => $id,
            'horse_id' => $this->horseId,
            'name' => $name,
            'kind' => $kind,
            'description' => null,
            'file_path' => "horse-documents/{$this->stableTenant->id}/{$this->horseId}/{$role}-{$id}_doc.pdf",
            'original_name' => 'doc.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 200000,
            'uploaded_by_role' => $role,
            'uploaded_by_user_id' => null,
            'uploaded_by_client_id' => $clientId,
            'valid_from' => null,
            'valid_until' => $validUntil,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'docs-st-'.$u,
            'name' => 'Docs Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'docs_st_'.$u,
            'db_username' => 'docs_st_'.substr($u, -8),
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
