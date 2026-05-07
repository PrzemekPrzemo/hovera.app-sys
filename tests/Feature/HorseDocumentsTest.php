<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HorseDocumentKind;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Services\Portal\ClientPortalAuth;
use App\Services\Stable\HorseDocumentService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class HorseDocumentsTest extends TestCase
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

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_doc_').'.sqlite';
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
            'name' => 'Marek',
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

    public function test_stable_can_upload_document(): void
    {
        $pdf = UploadedFile::fake()->create('paszport.pdf', 200, 'application/pdf');

        $doc = app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: $pdf,
            name: 'Paszport Bucefała',
            kind: HorseDocumentKind::Passport,
            description: 'Skan paszportu z 2024',
            validUntil: '2030-12-31',
        );

        $this->assertSame('stable', $doc->uploaded_by_role);
        $this->assertSame(HorseDocumentKind::Passport, $doc->kind);
        $this->assertSame('paszport.pdf', $doc->original_name);
        $this->assertStringContainsString('horse-documents/'.$this->tenant->id, $doc->file_path);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_client_can_upload_document_for_their_horse(): void
    {
        $jpg = UploadedFile::fake()->image('polisa.jpg');

        $doc = app(HorseDocumentService::class)->uploadByClient(
            tenant: $this->tenant,
            horse: $this->horse,
            clientId: $this->client->id,
            file: $jpg,
            name: 'Moja polisa',
            kind: HorseDocumentKind::Insurance,
        );

        $this->assertSame('client', $doc->uploaded_by_role);
        $this->assertSame($this->client->id, $doc->uploaded_by_client_id);
    }

    public function test_client_cannot_upload_for_someone_elses_horse(): void
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
        app(HorseDocumentService::class)->uploadByClient(
            tenant: $this->tenant,
            horse: $cudzy,
            clientId: $this->client->id,
            file: UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
            name: 'X',
            kind: HorseDocumentKind::Other,
        );
    }

    public function test_upload_rejects_oversize_file(): void
    {
        // Create a 30MB file — over our 25MB limit
        $big = UploadedFile::fake()->create('huge.pdf', 30 * 1024, 'application/pdf');

        $this->expectException(ValidationException::class);
        app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: $big,
            name: 'Big',
            kind: HorseDocumentKind::Other,
        );
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        $exe = UploadedFile::fake()->createWithContent('virus.exe', 'MZX');

        $this->expectException(ValidationException::class);
        app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: $exe,
            name: 'X',
            kind: HorseDocumentKind::Other,
        );
    }

    public function test_client_can_only_delete_own_uploads(): void
    {
        $stableDoc = app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: UploadedFile::fake()->create('umowa.pdf', 100, 'application/pdf'),
            name: 'Umowa pensjonatu',
            kind: HorseDocumentKind::Contract,
        );

        $this->expectException(ValidationException::class);
        app(HorseDocumentService::class)->delete($stableDoc, byClientId: $this->client->id);
    }

    public function test_expiring_within_scope_filters_correctly(): void
    {
        // Wygasł
        HorseDocument::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'name' => 'Stara polisa',
            'kind' => HorseDocumentKind::Insurance->value,
            'file_path' => 'fake/path1',
            'original_name' => 'x.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_by_role' => 'stable',
            'valid_until' => now()->subDays(5)->toDateString(),
        ]);
        // Wygasa za 14 dni → łapie scope expiringWithin(30)
        $expiring = HorseDocument::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'name' => 'Polisa wygasa',
            'kind' => HorseDocumentKind::Insurance->value,
            'file_path' => 'fake/path2',
            'original_name' => 'x.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_by_role' => 'stable',
            'valid_until' => now()->addDays(14)->toDateString(),
        ]);
        // Wygasa za 90 dni → out of 30d scope
        HorseDocument::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $this->horse->id,
            'name' => 'Polisa daleka',
            'kind' => HorseDocumentKind::Insurance->value,
            'file_path' => 'fake/path3',
            'original_name' => 'x.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_by_role' => 'stable',
            'valid_until' => now()->addDays(90)->toDateString(),
        ]);

        $within = HorseDocument::query()->expiringWithin(30)->get();
        $this->assertCount(1, $within);
        $this->assertSame($expiring->id, $within->first()->id);
    }

    public function test_portal_upload_creates_document(): void
    {
        $this->loginAs($this->client);

        $this->post(route('client_portal.horses.documents.upload', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]), [
            'name' => 'Moja polisa',
            'kind' => 'insurance',
            'description' => 'Wykupiona u XYZ',
            'file' => UploadedFile::fake()->create('polisa.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, HorseDocument::query()->count());
        $doc = HorseDocument::query()->first();
        $this->assertSame('client', $doc->uploaded_by_role);
        $this->assertSame($this->client->id, $doc->uploaded_by_client_id);
    }

    public function test_portal_download_returns_file(): void
    {
        $doc = app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: UploadedFile::fake()->create('paszport.pdf', 100, 'application/pdf'),
            name: 'Paszport',
            kind: HorseDocumentKind::Passport,
        );
        $this->loginAs($this->client);

        $response = $this->get(route('client_portal.horses.documents.download', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
            'document' => $doc->id,
        ]));

        $response->assertOk();
        $this->assertSame('attachment; filename=paszport.pdf', $response->headers->get('content-disposition'));
    }

    public function test_portal_blocks_download_for_other_clients_horse(): void
    {
        $doc = app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: UploadedFile::fake()->create('secret.pdf', 100, 'application/pdf'),
            name: 'Tajny',
            kind: HorseDocumentKind::Other,
        );

        $other = Client::create(['id' => (string) Str::ulid(), 'name' => 'Inny']);
        $this->loginAs($other);

        $this->get(route('client_portal.horses.documents.download', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
            'document' => $doc->id,
        ]))->assertNotFound();
    }

    public function test_portal_can_delete_own_upload_but_not_stables(): void
    {
        $myDoc = app(HorseDocumentService::class)->uploadByClient(
            tenant: $this->tenant,
            horse: $this->horse,
            clientId: $this->client->id,
            file: UploadedFile::fake()->create('moje.pdf', 100, 'application/pdf'),
            name: 'Moje',
            kind: HorseDocumentKind::Other,
        );
        $stableDoc = app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: UploadedFile::fake()->create('stajnia.pdf', 100, 'application/pdf'),
            name: 'Stajnia upload',
            kind: HorseDocumentKind::Contract,
        );

        $this->loginAs($this->client);

        // Własny → ok
        $this->delete(route('client_portal.horses.documents.delete', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
            'document' => $myDoc->id,
        ]))->assertRedirect();

        $this->assertSoftDeleted('horse_documents', ['id' => $myDoc->id], 'tenant');

        // Cudzy (stajni) → odrzucone (validation error → back z errors)
        $this->delete(route('client_portal.horses.documents.delete', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
            'document' => $stableDoc->id,
        ]))->assertSessionHasErrors();

        // Wciąż istnieje
        $this->assertNotSoftDeleted('horse_documents', ['id' => $stableDoc->id], 'tenant');
    }

    public function test_portal_horse_view_shows_documents_section(): void
    {
        app(HorseDocumentService::class)->uploadByStable(
            tenant: $this->tenant,
            horse: $this->horse,
            file: UploadedFile::fake()->create('paszport.pdf', 100, 'application/pdf'),
            name: 'Paszport Bucefała',
            kind: HorseDocumentKind::Passport,
        );
        $this->loginAs($this->client);

        $response = $this->get(route('client_portal.horses.show', [
            'slug' => $this->tenant->slug,
            'horse' => $this->horse->id,
        ]));

        $response->assertOk()
            ->assertSee('Dokumenty')
            ->assertSee('Paszport Bucefała')
            ->assertSee('Wgraj dokument');
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
            'slug' => 'doc-'.$u,
            'name' => 'Stable',
            'db_name' => 'doc_'.$u,
            'db_username' => 'doc_'.substr($u, -8),
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
            $t->date('birth_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
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

        // Inne tabele needed by Horse eager-loads
        foreach (['boxes', 'boarding_services', 'stable_activities', 'health_records', 'horse_messages'] as $tbl) {
            Schema::connection('tenant')->create($tbl, function ($t) {
                $t->string('id', 26)->primary();
                $t->string('horse_id', 26)->nullable();
                $t->string('name', 200)->nullable();
                $t->string('kind', 32)->nullable();
                $t->string('type', 32)->nullable();
                $t->dateTime('performed_at')->nullable();
                $t->date('next_due_at')->nullable();
                $t->text('summary')->nullable();
                $t->text('body')->nullable();
                $t->json('attachments')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('read_by_client_at')->nullable();
                $t->timestamp('read_by_stable_at')->nullable();
                $t->string('client_id', 26)->nullable();
                $t->string('direction', 32)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('updated_at')->useCurrent();
                $t->timestamp('deleted_at')->nullable();
            });
        }

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
    }
}
