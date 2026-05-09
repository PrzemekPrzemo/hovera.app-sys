<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Stable\SendHorseMessage;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseMessage;
use App\Notifications\HorseMessageNotification;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class HorseMessagesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    private Horse $horse;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_msg_').'.sqlite';
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

    public function test_stable_can_send_message_to_horse_owner(): void
    {
        Notification::fake();

        $msg = app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $this->horse,
            body: 'Bucefał dziś wyraźnie kuleje na lewy przód, prosimy o weterynarza.',
            subject: 'Kontuzja',
        );

        $this->assertSame('from_stable', $msg->direction);
        $this->assertSame($this->client->id, $msg->client_id);
        $this->assertSame('Kontuzja', $msg->subject);
        $this->assertNotNull($msg->sent_at);
        $this->assertNull($msg->read_by_client_at);

        Notification::assertSentOnDemand(HorseMessageNotification::class);
    }

    public function test_stable_send_throws_when_horse_has_no_owner(): void
    {
        $orphan = Horse::create([
            'id' => '01HHORSE000000000000000002',
            'name' => 'Bezpański',
            // brak owner_client_id
        ]);

        $this->expectException(ValidationException::class);
        app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $orphan,
            body: 'test',
        );
    }

    public function test_client_can_send_message_about_their_horse(): void
    {
        Notification::fake();

        $msg = app(SendHorseMessage::class)->fromClient(
            tenant: $this->tenant,
            horse: $this->horse,
            clientId: $this->client->id,
            body: 'Czy mogę odebrać paszport jutro?',
            subject: 'Paszport',
        );

        $this->assertSame('from_client', $msg->direction);
        $this->assertSame($this->client->id, $msg->client_id);
        $this->assertNull($msg->read_by_stable_at);
    }

    public function test_client_send_throws_for_someone_elses_horse(): void
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

        $this->expectException(ValidationException::class);
        app(SendHorseMessage::class)->fromClient(
            tenant: $this->tenant,
            horse: $cudzy,
            clientId: $this->client->id, // klient próbuje pisać o cudzym koniu
            body: 'test',
        );
    }

    public function test_send_with_attachments_stores_files(): void
    {
        $jpg = UploadedFile::fake()->image('zdjecie-konia.jpg', 800, 600);

        $msg = app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $this->horse,
            body: 'Foto z dziś',
            attachments: [$jpg],
        );

        $atts = $msg->attachments;
        $this->assertCount(1, $atts);
        $this->assertSame('zdjecie-konia.jpg', $atts[0]['original_name']);
        $this->assertStringContainsString('horse-messages/'.$this->tenant->id, $atts[0]['path']);
        $this->assertStringContainsString($this->horse->id, $atts[0]['path']);
        Storage::disk('local')->assertExists($atts[0]['path']);
    }

    public function test_send_rejects_too_many_attachments(): void
    {
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = UploadedFile::fake()->image("foto-{$i}.jpg");
        }

        $this->expectException(ValidationException::class);
        app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $this->horse,
            body: 'test',
            attachments: $files,
        );
    }

    public function test_send_rejects_disallowed_mime(): void
    {
        // Symulujemy plik wykonywalny — niedozwolony
        $exe = UploadedFile::fake()->createWithContent('virus.exe', 'MZ\x90\x00');

        $this->expectException(ValidationException::class);
        app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $this->horse,
            body: 'test',
            attachments: [$exe],
        );
    }

    public function test_unread_scope_filters_correctly(): void
    {
        // Jedna wiadomość od stajni (klient nie przeczytał)
        $unread = HorseMessage::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'direction' => 'from_stable',
            'client_id' => $this->client->id,
            'body' => 'X',
            'sent_at' => now(),
        ]);
        // Druga już przeczytana
        HorseMessage::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'direction' => 'from_stable',
            'client_id' => $this->client->id,
            'body' => 'Y',
            'sent_at' => now()->subHour(),
            'read_by_client_at' => now(),
        ]);

        $unreadList = HorseMessage::query()->forClient($this->client->id)->unreadByClient()->get();
        $this->assertCount(1, $unreadList);
        $this->assertSame($unread->id, $unreadList->first()->id);
    }

    public function test_portal_show_horse_marks_messages_as_read(): void
    {
        HorseMessage::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'direction' => 'from_stable',
            'client_id' => $this->client->id,
            'body' => 'Ważne info',
            'sent_at' => now()->subMinutes(5),
        ]);
        $this->loginAs($this->client);

        $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]))->assertOk()->assertSee('Ważne info');

        $this->assertSame(0, HorseMessage::query()->unreadByClient()->count());
    }

    public function test_portal_send_message_form_creates_record(): void
    {
        Notification::fake();
        $this->loginAs($this->client);

        $this->post(route('client_portal.horses.messages.send', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]), [
            'subject' => 'Test',
            'body' => 'Treść z portalu klienta',
        ])->assertRedirect();

        $this->assertSame(1, HorseMessage::query()->where('direction', 'from_client')->count());
    }

    public function test_portal_attachment_download_returns_file(): void
    {
        $msg = app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $this->horse,
            body: 'Foto',
            attachments: [UploadedFile::fake()->image('bucephal.jpg')],
        );
        $this->loginAs($this->client);

        $response = $this->get(route('client_portal.horses.messages.attachment', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
            'message' => $msg->id,
            'index' => 0,
        ]));

        $response->assertOk();
        $this->assertSame('attachment; filename=bucephal.jpg', $response->headers->get('content-disposition'));
    }

    public function test_portal_blocks_attachment_download_for_other_clients_horse(): void
    {
        $msg = app(SendHorseMessage::class)->fromStable(
            tenant: $this->tenant,
            horse: $this->horse,
            body: 'Foto',
            attachments: [UploadedFile::fake()->image('priv.jpg')],
        );

        // Login as DIFFERENT client
        $other = Client::create([
            'id' => (string) Str::ulid(),
            'name' => 'Inny',
        ]);
        $this->loginAs($other);

        $this->get(route('client_portal.horses.messages.attachment', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
            'message' => $msg->id,
            'index' => 0,
        ]))->assertNotFound();
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
            'slug' => 'msg-'.$u,
            'name' => 'Stable',
            'db_name' => 'msg_'.$u,
            'db_username' => 'msg_'.substr($u, -8),
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

        Schema::connection('tenant')->create('boxes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 60);
            $t->string('type', 32)->default('indoor');
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('boarding_services', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('unit', 32)->default('szt.');
            $t->string('frequency', 32);
            $t->unsignedInteger('price_cents');
            $t->boolean('is_active')->default(true);
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

        Schema::connection('tenant')->create('horse_photos', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('file_path', 500);
            $t->string('original_name', 255);
            $t->string('mime', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->string('caption', 255)->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('uploaded_by_role', 16)->default('stable');
            $t->string('uploaded_by_user_id', 26)->nullable();
            $t->string('uploaded_by_client_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('stable_activities', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->timestamp('performed_at');
            $t->string('summary', 200)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('summary', 255);
            $t->date('next_due_at')->nullable();
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
