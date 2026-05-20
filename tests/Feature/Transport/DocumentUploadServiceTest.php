<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Verification\DocumentUploadService;
use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_du_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Storage::fake('local');
        config()->set('transport.documents.disk', 'local');

        $this->setUpDocsTable();
        $this->tenant = $this->makeTenant(VerificationStatus::Pending);

        // Mock TenantManager: setCurrent w produkcji przepina connection na
        // MySQL z tenant.db_host — w testach sqlite by wybuchło. Trzymamy
        // tenant w closure, current()/tenantOrFail() zwracają go.
        $held = $this->tenant;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$held) {
                if ($held === null) {
                    throw new \RuntimeException('No tenant');
                }

                return $held;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_upload_stores_file_and_creates_document(): void
    {
        $file = UploadedFile::fake()->create('krs.pdf', 100, 'application/pdf');

        $doc = app(DocumentUploadService::class)->upload(
            $file,
            TransporterDocumentType::CompanyRegistration,
        );

        $this->assertSame('krs.pdf', $doc->original_filename);
        $this->assertSame(TransporterDocument::STATUS_PENDING, $doc->status);
        $this->assertSame(TransporterDocumentType::CompanyRegistration, $doc->document_type);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        $file = UploadedFile::fake()->create('virus.exe', 5, 'application/x-msdownload');

        $this->expectException(\RuntimeException::class);
        app(DocumentUploadService::class)->upload($file, TransporterDocumentType::Other);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        // 11 MB > 10 MB limit
        $file = UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf');

        $this->expectException(\RuntimeException::class);
        app(DocumentUploadService::class)->upload($file, TransporterDocumentType::Other);
    }

    public function test_uploading_all_required_promotes_to_under_review(): void
    {
        $service = app(DocumentUploadService::class);

        foreach (TransporterDocumentType::requiredCases() as $type) {
            $file = UploadedFile::fake()->create($type->value.'.pdf', 50, 'application/pdf');
            $service->upload($file, $type);
        }

        $this->assertSame(VerificationStatus::UnderReview, $this->tenant->fresh()->verification_status);
        $this->assertTrue($service->hasAllRequired());
    }

    public function test_uploading_only_some_required_stays_pending(): void
    {
        $service = app(DocumentUploadService::class);

        $file = UploadedFile::fake()->create('krs.pdf', 50, 'application/pdf');
        $service->upload($file, TransporterDocumentType::CompanyRegistration);

        $this->assertSame(VerificationStatus::Pending, $this->tenant->fresh()->verification_status);
        $this->assertFalse($service->hasAllRequired());
    }

    public function test_destroy_removes_file_and_demotes_status(): void
    {
        $service = app(DocumentUploadService::class);
        foreach (TransporterDocumentType::requiredCases() as $type) {
            $service->upload(
                UploadedFile::fake()->create($type->value.'.pdf', 50, 'application/pdf'),
                $type,
            );
        }
        $this->assertSame(VerificationStatus::UnderReview, $this->tenant->fresh()->verification_status);

        $oneDoc = TransporterDocument::query()
            ->where('document_type', TransporterDocumentType::CompanyRegistration->value)
            ->first();

        $service->destroy($oneDoc);

        // Status demoted bo brakuje teraz tego typu
        $this->assertSame(VerificationStatus::Pending, $this->tenant->fresh()->verification_status);
    }

    public function test_destroy_blocked_for_verified_document(): void
    {
        $service = app(DocumentUploadService::class);
        $doc = TransporterDocument::create([
            'id' => (string) Str::ulid(),
            'document_type' => TransporterDocumentType::AnimalTransportCert,
            'status' => TransporterDocument::STATUS_VERIFIED,
            'file_path' => 'transporter-docs/test/cert.pdf',
        ]);

        $this->expectException(\RuntimeException::class);
        $service->destroy($doc);
    }

    public function test_re_upload_pending_replaces_existing_record(): void
    {
        $service = app(DocumentUploadService::class);

        $first = $service->upload(
            UploadedFile::fake()->create('krs-v1.pdf', 50, 'application/pdf'),
            TransporterDocumentType::CompanyRegistration,
        );
        $second = $service->upload(
            UploadedFile::fake()->create('krs-v2.pdf', 50, 'application/pdf'),
            TransporterDocumentType::CompanyRegistration,
        );

        $this->assertSame($first->id, $second->id, 'pending re-upload must replace, not duplicate');
        $this->assertSame('krs-v2.pdf', $second->fresh()->original_filename);
        $this->assertSame(1, TransporterDocument::count());
    }

    public function test_route_is_registered_for_documents_page(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('filament.transport.pages.transporter-documents'));
    }

    public function test_reupload_after_rejection_promotes_tenant_back_to_under_review(): void
    {
        // Scenariusz: master admin odrzucił całe konto (Rejected). Transporter
        // wgrał poprawione dokumenty. Reguła: tenant wraca do UnderReview żeby
        // master admin zobaczył kolejkę re-weryfikacji. Bez tego rejected
        // tenant utykał na koniec mimo poprawek.
        $tenant = $this->tenant;
        $tenant->forceFill(['verification_status' => VerificationStatus::Rejected])->save();

        $service = app(DocumentUploadService::class);
        foreach (TransporterDocumentType::requiredCases() as $type) {
            $service->upload(
                UploadedFile::fake()->create($type->value.'.pdf', 50, 'application/pdf'),
                $type,
            );
        }

        $this->assertSame(VerificationStatus::UnderReview, $tenant->fresh()->verification_status);
    }

    private function makeTenant(VerificationStatus $vs): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 'firma_'.uniqid(),
            'db_username' => 'firma_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function setUpDocsTable(): void
    {
        Schema::connection('tenant')->create('transporter_documents', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('document_type', 32);
            $t->string('status', 16)->default('pending');
            $t->string('file_path');
            $t->unsignedInteger('file_size')->nullable();
            $t->string('file_mime', 96)->nullable();
            $t->string('original_filename')->nullable();
            $t->date('expires_at')->nullable();
            $t->date('issued_at')->nullable();
            $t->string('verified_by_user_id', 26)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
