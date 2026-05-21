<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Messages;

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
 * HTTP tests dla `/api/owner/.../messages*` endpoint'ów. Sanctum SPA
 * mode (actingAs ustawia session).
 */
class OwnerMessagesApiTest extends TestCase
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

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_msgsapi_').'.sqlite';
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
        $response = $this->getJson('/api/owner/horses/'.$this->centralHorseId.'/messages');
        $response->assertUnauthorized();
    }

    public function test_index_returns_messages_for_owner_horse(): void
    {
        $this->makeActiveBoarding();
        $this->seedMessage('from_stable', 'Witamy', '2026-05-01 09:00');
        $this->seedMessage('from_client', 'Dzięki!', '2026-05-01 10:00');

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/messages');

        $response->assertOk();
        $response->assertJsonPath('count', 2);
        $response->assertJsonPath('data.0.subject', 'Witamy');
        $response->assertJsonPath('data.0.direction', 'from_stable');
    }

    public function test_index_returns_403_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $response = $this->actingAs($other)
            ->getJson('/api/owner/horses/'.$this->centralHorseId.'/messages');

        $response->assertForbidden();
    }

    public function test_send_creates_from_client_message(): void
    {
        $this->makeActiveBoarding();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages', [
                'subject' => 'Pytanie',
                'body' => 'Jak idzie trening?',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.direction', 'from_client');
        $response->assertJsonPath('data.body', 'Jak idzie trening?');
    }

    public function test_send_returns_422_for_invalid_body(): void
    {
        $this->makeActiveBoarding();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages', [
                'subject' => 'X',
                // body missing — required
            ]);

        $response->assertStatus(422);
    }

    public function test_send_returns_403_for_ended_boarding(): void
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

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages', [
                'body' => 'Hello after end',
            ]);

        $response->assertForbidden();
    }

    public function test_send_validates_attachments_max_size(): void
    {
        $this->makeActiveBoarding();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages', [
                'body' => 'Big file',
                'attachments' => [
                    ['path' => 'x/y.bin', 'size' => 26214401], // 25MB + 1 byte = invalid
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_mark_read_endpoint_updates_timestamp(): void
    {
        $this->makeActiveBoarding();
        $messageId = $this->seedMessage('from_stable', 'X', '2026-05-01 09:00');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/messages/'.$this->stableTenant->id.'/'.$messageId.'/read');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertNotNull(DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at'));
    }

    public function test_mark_read_silent_for_unknown_stable(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/messages/'.(string) Str::ulid().'/'.(string) Str::ulid().'/read');

        // Silent OK — nie ujawniamy istnienia message ID.
        $response->assertOk();
    }

    public function test_unread_count_endpoint_returns_total(): void
    {
        $this->makeActiveBoarding();
        $this->seedMessage('from_stable', 'M1', '2026-05-01 09:00');
        $this->seedMessage('from_stable', 'M2', '2026-05-02 09:00');

        $response = $this->actingAs($this->owner)
            ->getJson('/api/owner/messages/unread-count');

        $response->assertOk();
        $response->assertJsonPath('count', 2);
    }

    public function test_upload_attachment_returns_metadata(): void
    {
        Storage::fake('local');
        $this->makeActiveBoarding();
        $file = UploadedFile::fake()->image('iskra.jpg');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages/attachments/upload', [
                'file' => $file,
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['path', 'original_name', 'mime', 'size', 'uploader']]);
        $response->assertJsonPath('data.original_name', 'iskra.jpg');
        $response->assertJsonPath('data.uploader', 'owner');
    }

    public function test_upload_attachment_returns_403_for_ended_boarding(): void
    {
        Storage::fake('local');
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages/attachments/upload', [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ]);

        $response->assertForbidden();
    }

    public function test_upload_attachment_returns_422_for_oversized_file(): void
    {
        Storage::fake('local');
        $this->makeActiveBoarding();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages/attachments/upload', [
                'file' => UploadedFile::fake()->create('huge.pdf', 26 * 1024, 'application/pdf'),
            ]);

        $response->assertStatus(422);
    }

    public function test_upload_attachment_returns_403_when_not_owner(): void
    {
        Storage::fake('local');
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $response = $this->actingAs($other)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages/attachments/upload', [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ]);

        $response->assertForbidden();
    }

    public function test_download_attachment_streams_file(): void
    {
        Storage::fake('local');
        $this->makeActiveBoarding();

        // Najpierw upload
        $uploadResponse = $this->actingAs($this->owner)
            ->postJson('/api/owner/horses/'.$this->centralHorseId.'/messages/attachments/upload', [
                'file' => UploadedFile::fake()->image('iskra.jpg'),
            ]);
        $uploadResponse->assertCreated();
        $attachment = $uploadResponse->json('data');

        // Zapisz wiadomość z tym attachment
        $messageId = $this->seedMessageWithAttachments('from_client', 'Z fotą', '2026-05-01 09:00', [$attachment]);

        // Download
        $response = $this->actingAs($this->owner)
            ->get('/api/owner/messages/'.$this->stableTenant->id.'/'.$messageId.'/attachments/0/download');

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_download_attachment_returns_404_for_unknown_message(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/api/owner/messages/'.$this->stableTenant->id.'/'.(string) Str::ulid().'/attachments/0/download');

        $response->assertNotFound();
    }

    public function test_download_attachment_returns_404_for_invalid_index(): void
    {
        $this->makeActiveBoarding();
        $messageId = $this->seedMessage('from_stable', 'No attachment', '2026-05-01 09:00');

        $response = $this->actingAs($this->owner)
            ->get('/api/owner/messages/'.$this->stableTenant->id.'/'.$messageId.'/attachments/5/download');

        $response->assertNotFound();
    }

    public function test_download_attachment_blocks_cross_tenant_path_injection(): void
    {
        Storage::fake('local');
        $this->makeActiveBoarding();
        // Podstawiamy ścieżkę z OBCEGO tenant'a w attachments JSON.
        $maliciousAttachment = [
            'path' => 'horse-messages/OTHER_TENANT/x/y.jpg',
            'original_name' => 'stolen.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
        ];
        $messageId = $this->seedMessageWithAttachments('from_client', 'Hack', '2026-05-01 09:00', [$maliciousAttachment]);

        $response = $this->actingAs($this->owner)
            ->get('/api/owner/messages/'.$this->stableTenant->id.'/'.$messageId.'/attachments/0/download');

        // 403 — pathBelongsToStable() wykrywa cudze ścieżki nawet jeśli
        // attachment jest w "naszej" wiadomości.
        $response->assertForbidden();
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

    private function seedMessage(string $direction, string $subject, string $sentAt): string
    {
        return $this->seedMessageWithAttachments($direction, $subject, $sentAt, []);
    }

    /**
     * @param  list<array<string,mixed>>  $attachments
     */
    private function seedMessageWithAttachments(string $direction, string $subject, string $sentAt, array $attachments): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horse_messages')->insert([
            'id' => $id,
            'horse_id' => $this->horseId,
            'direction' => $direction,
            'sender_user_id' => $direction === 'from_stable' ? (string) Str::ulid() : $this->owner->id,
            'client_id' => $this->clientId,
            'subject' => $subject,
            'body' => 'Body of '.$subject,
            'attachments' => $attachments !== [] ? json_encode($attachments) : null,
            'sent_at' => $sentAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);

        return $id;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'msgsapi-st-'.$u,
            'name' => 'MsgsApi Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'msgsapi_st_'.$u,
            'db_username' => 'msgsapi_st_'.substr($u, -8),
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
    }
}
