<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\TransporterDocument;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Master admin podgląd / pobranie dokumentów weryfikacyjnych z poziomu
 * `/admin/transporters/{id}/edit` (relation manager → row actions
 * `preview_doc` / `download_doc`).
 */
class TransporterDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        // In-memory'esque tenant DB — kopiujemy wzorzec z TodayDashboardTrendTest.
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hov_doc_dl_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('transporter_documents', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('document_type', 64);
            $t->string('status', 32);
            $t->string('file_path', 255)->nullable();
            $t->integer('file_size')->nullable();
            $t->string('file_mime', 100)->nullable();
            $t->string('original_filename', 255)->nullable();
            $t->date('expires_at')->nullable();
            $t->date('issued_at')->nullable();
            $t->string('verified_by_user_id', 26)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamp('expiry_notified_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        config()->set('transport.documents.disk', 'transporter-docs-test');
        Storage::fake('transporter-docs-test');

        // Nie wymuszamy 2FA dla master admin'a w testach — `EnsureMasterAdmin`
        // przy require_2fa zrobiłby redirect do `/two-factor/setup`.
        config()->set('hovera.admin.require_2fa', false);

        $this->mock(MasterAuditLogger::class, function (MockInterface $m): void {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_master_admin_can_preview_document_inline(): void
    {
        [$tenant, $document] = $this->seedTransporterWithDocument();

        $this->actingAsMasterAdmin();
        $response = $this->get(route('admin.transporter.document.preview', [
            'tenant' => $tenant->id,
            'document' => $document->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_master_admin_can_download_document_as_attachment(): void
    {
        [$tenant, $document] = $this->seedTransporterWithDocument();

        $this->actingAsMasterAdmin();
        $response = $this->get(route('admin.transporter.document.download', [
            'tenant' => $tenant->id,
            'document' => $document->id,
        ]));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        // Filename znormalizowany do {slug}_{type}_*.pdf — żeby admin
        // od razu miał czytelną nazwę pliku przy save-as do anonimizacji.
        $this->assertStringContainsString($tenant->slug, $disposition);
    }

    public function test_non_master_admin_is_blocked(): void
    {
        [$tenant, $document] = $this->seedTransporterWithDocument();

        $regular = User::create([
            'email' => 'regular-'.uniqid().'@example.com',
            'name' => 'Regular',
            'password' => Hash::make('secret'),
            'is_master_admin' => false,
        ]);
        $this->actingAs($regular);

        $response = $this->get(route('admin.transporter.document.preview', [
            'tenant' => $tenant->id,
            'document' => $document->id,
        ]));

        // EnsureMasterAdmin redirectuje tenant userów na /app, nie 403.
        $response->assertRedirect('/app');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$tenant, $document] = $this->seedTransporterWithDocument();

        $response = $this->get(route('admin.transporter.document.download', [
            'tenant' => $tenant->id,
            'document' => $document->id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('login', (string) $response->headers->get('location'));
    }

    public function test_404_when_tenant_is_not_transporter(): void
    {
        // Stable tenant z istniejącym docs row — nie pozwalamy podejrzeć.
        // (Endpoint scope'owany do transporterów; stable nie ma tego flow'a.)
        [$transporter, $document] = $this->seedTransporterWithDocument();
        $stable = Tenant::create([
            'slug' => 'stable-'.uniqid(),
            'name' => 'Stable',
            'type' => TenantType::Stable,
            'db_name' => 'x_'.uniqid(),
            'db_username' => 'x_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $this->actingAsMasterAdmin();
        $response = $this->get(route('admin.transporter.document.preview', [
            'tenant' => $stable->id,
            'document' => $document->id,
        ]));

        $response->assertNotFound();
    }

    public function test_404_when_document_file_missing_on_disk(): void
    {
        [$tenant] = $this->seedTransporterWithDocument();

        $orphan = $this->insertDocumentRow($tenant, filePath: 'transporter-docs/nonexistent.pdf');

        $this->actingAsMasterAdmin();
        $response = $this->get(route('admin.transporter.document.preview', [
            'tenant' => $tenant->id,
            'document' => $orphan,
        ]));

        $response->assertNotFound();
    }

    /** @return array{0: Tenant, 1: TransporterDocument} */
    private function seedTransporterWithDocument(): array
    {
        $tenant = $this->makeTransporter();
        $this->bindTenant($tenant);

        // Zapisujemy plik na fake dysku — `Storage::disk()->response()`
        // znajdzie go przy stream'owaniu.
        $disk = Storage::disk('transporter-docs-test');
        $path = 'transporter-docs/'.$tenant->id.'/test-document.pdf';
        $disk->put($path, UploadedFile::fake()->create('test.pdf', 50, 'application/pdf')->get());

        $documentId = $this->insertDocumentRow($tenant, filePath: $path);

        return [$tenant, TransporterDocument::query()->find($documentId)];
    }

    private function makeTransporter(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'tr-doc-'.$u,
            'name' => 'Doc Transporter '.$u,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::UnderReview,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'provisioning',
        ]);
    }

    private function insertDocumentRow(Tenant $tenant, string $filePath): string
    {
        $id = (string) Str::ulid();
        $this->bindTenant($tenant);
        DB::connection('tenant')->table('transporter_documents')->insert([
            'id' => $id,
            'document_type' => TransporterDocumentType::RoadCarrierLicense->value,
            'status' => TransporterDocument::STATUS_PENDING,
            'file_path' => $filePath,
            'file_size' => 50 * 1024,
            'file_mime' => 'application/pdf',
            'original_filename' => 'licencja.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function bindTenant(Tenant $tenant): void
    {
        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'master-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }
}
