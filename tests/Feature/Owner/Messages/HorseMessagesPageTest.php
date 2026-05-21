<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Messages;

use App\Enums\TenantType;
use App\Filament\Owner\Pages\HorseMessages;
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
 * Pokrywa Filament Page HorseMessages — mount + access gate + auto
 * mark-read. Send flow pokryty w OwnerMessagesApiTest + OwnerMessagesServiceTest;
 * tutaj sprawdzamy że Page wire'uje się correctly.
 */
class HorseMessagesPageTest extends TestCase
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

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_msgspage_').'.sqlite';
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
        $this->horseId = $this->seedHorse($this->centralHorseId);
        $this->clientId = $this->seedClient($this->owner->id);

        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_mount_with_active_boarding_loads_thread_and_enables_send(): void
    {
        $this->makeActiveBoarding();
        $this->seedMessage('from_stable', 'Witamy', '2026-05-01 09:00');
        $this->seedMessage('from_client', 'Cześć', '2026-05-01 10:00');

        $this->actingAs($this->owner);
        $page = new HorseMessages;
        $page->mount($this->centralHorseId);

        $this->assertCount(2, $page->thread);
        $this->assertSame('Witamy', $page->thread[0]->subject);
        $this->assertTrue($page->canSend);
        $this->assertSame($this->stableTenant->id, $page->stableTenant->id);
    }

    public function test_mount_allows_read_for_ended_boarding_but_disables_send(): void
    {
        // Per Q3 roadmap: ended boarding zachowuje read access.
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $this->centralHorseId,
            'stable_tenant_id' => $this->stableTenant->id,
            'owner_user_id' => $this->owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);
        $this->seedMessage('from_stable', 'Old', '2025-12-01 10:00');

        $this->actingAs($this->owner);
        $page = new HorseMessages;
        $page->mount($this->centralHorseId);

        $this->assertCount(1, $page->thread);
        $this->assertFalse($page->canSend, 'send disabled gdy tylko ended boarding');
    }

    public function test_mount_aborts_403_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        $this->actingAs($other);
        $page = new HorseMessages;
        try {
            $page->mount($this->centralHorseId);
            $this->fail('Expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_mount_aborts_403_when_no_assignment_at_all(): void
    {
        // Owner posiada konia (primary_owner) ale nigdy nie miał boarding'u
        // — zwykły CRUD horse, bez stajni. Mount zwraca 403 bo nie ma czego
        // pokazać (thread dotyczy konkretnej stajni).
        $this->actingAs($this->owner);
        $page = new HorseMessages;
        try {
            $page->mount($this->centralHorseId);
            $this->fail('Expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_mount_auto_marks_unread_from_stable_messages(): void
    {
        $this->makeActiveBoarding();
        $messageId = $this->seedMessage('from_stable', 'Auto-read me', '2026-05-01 09:00');

        $this->assertNull(DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at'));

        $this->actingAs($this->owner);
        $page = new HorseMessages;
        $page->mount($this->centralHorseId);

        // Po mount'cie read_by_client_at powinno być ustawione
        $this->assertNotNull(DB::connection('tenant')->table('horse_messages')
            ->where('id', $messageId)->value('read_by_client_at'));
    }

    public function test_is_image_attachment_helper_works(): void
    {
        $page = new HorseMessages;
        $this->assertTrue($page->isImageAttachment(['mime' => 'image/jpeg']));
        $this->assertTrue($page->isImageAttachment(['mime' => 'image/png']));
        $this->assertTrue($page->isImageAttachment(['mime' => 'image/webp']));
        $this->assertFalse($page->isImageAttachment(['mime' => 'application/pdf']));
        $this->assertFalse($page->isImageAttachment(['mime' => 'video/mp4']));
        $this->assertFalse($page->isImageAttachment([]));
    }

    public function test_format_file_size_handles_bytes_kb_mb(): void
    {
        $page = new HorseMessages;
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

    private function seedMessage(string $direction, string $subject, string $sentAt): string
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
            'slug' => 'msgs-page-'.$u,
            'name' => 'Messages Page Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'msgs_page_'.$u,
            'db_username' => 'msgs_pg_'.substr($u, -8),
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
