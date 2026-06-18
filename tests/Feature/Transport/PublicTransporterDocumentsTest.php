<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
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
 * Publiczne wyświetlanie zanonimizowanych dokumentów na profilu transportera.
 * Pokrywa:
 *   - endpoint `/t/{slug}/dokumenty/{document}` strumieniuje plik gdy wszystkie
 *     gating'i przejdą (tenant verified, doc verified, public_file_path set)
 *   - 404 gdy doc nie ma public_file_path (brak zanonimizowanej wersji)
 *   - 404 gdy doc status != verified (pending/rejected nie pokazujemy)
 *   - 404 gdy tenant nie jest verified transporter (niezweryfikowane firmy
 *     są niewidoczne publicznie globalnie)
 *   - 404 gdy plik fizycznie zniknął z dysku (orphaned row)
 *   - hasPublicVersion() helper na modelu
 */
class PublicTransporterDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hov_pub_doc_').'.sqlite';
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
            $t->string('public_file_path', 255)->nullable();
            $t->integer('public_file_size')->nullable();
            $t->string('public_file_mime', 100)->nullable();
            $t->timestamp('public_uploaded_at')->nullable();
            $t->string('public_uploaded_by_user_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        config()->set('transport.documents.disk', 'pub-docs-test');
        Storage::fake('pub-docs-test');
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_serves_anonymized_document_inline_with_long_cache(): void
    {
        [$tenant, $document] = $this->seedVerifiedTransporterWithPublicDocument();

        $response = $this->get(route('public.transporter.document', [
            'slug' => $tenant->slug,
            'document' => $document->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        // Cache-Control musi pozwolić CDN'owi cache'ować — strumień PDFa
        // z PHP jest drogi, a zanonimizowany dokument nie zmienia się często.
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('cache-control'));
    }

    public function test_404_when_document_has_no_public_version(): void
    {
        [$tenant] = $this->seedVerifiedTransporterWithPublicDocument();

        // Drugi doc: verified ale BEZ public_file_path.
        $orphan = $this->insertDocument($tenant, status: TransporterDocument::STATUS_VERIFIED, publicFilePath: null);

        $response = $this->get(route('public.transporter.document', [
            'slug' => $tenant->slug,
            'document' => $orphan,
        ]));

        $response->assertNotFound();
    }

    public function test_404_when_document_status_is_pending_even_with_public_path(): void
    {
        [$tenant] = $this->seedVerifiedTransporterWithPublicDocument();

        // Edge case: ktoś nadpisał DB ręcznie, public_file_path jest ale status=pending.
        // Model::hasPublicVersion() chroni — pending docs nie wychodzą do świata.
        $path = $this->seedFileOnDisk($tenant->id);
        $docId = $this->insertDocument($tenant, status: TransporterDocument::STATUS_PENDING, publicFilePath: $path);

        $response = $this->get(route('public.transporter.document', [
            'slug' => $tenant->slug,
            'document' => $docId,
        ]));

        $response->assertNotFound();
    }

    public function test_404_when_tenant_is_not_verified_transporter(): void
    {
        // Pending tenant: nawet jeśli ma dokument z public_file_path,
        // publiczny profil jest niewidoczny → endpoint też zwraca 404.
        $tenant = $this->makeTransporter(verification: VerificationStatus::Pending);
        $this->bindTenant($tenant);
        $path = $this->seedFileOnDisk($tenant->id);
        $docId = $this->insertDocument($tenant, status: TransporterDocument::STATUS_VERIFIED, publicFilePath: $path);

        $response = $this->get(route('public.transporter.document', [
            'slug' => $tenant->slug,
            'document' => $docId,
        ]));

        $response->assertNotFound();
    }

    public function test_404_when_file_missing_on_disk(): void
    {
        [$tenant] = $this->seedVerifiedTransporterWithPublicDocument();
        $orphan = $this->insertDocument(
            $tenant,
            status: TransporterDocument::STATUS_VERIFIED,
            publicFilePath: 'transporter-docs/'.$tenant->id.'/public/missing.pdf',
        );

        $response = $this->get(route('public.transporter.document', [
            'slug' => $tenant->slug,
            'document' => $orphan,
        ]));

        $response->assertNotFound();
    }

    public function test_has_public_version_helper_requires_verified_status(): void
    {
        $tenant = $this->makeTransporter(verification: VerificationStatus::Verified);
        $this->bindTenant($tenant);

        $verified = TransporterDocument::create($this->row([
            'status' => TransporterDocument::STATUS_VERIFIED,
            'public_file_path' => 'x.pdf',
        ]));
        $pending = TransporterDocument::create($this->row([
            'status' => TransporterDocument::STATUS_PENDING,
            'public_file_path' => 'x.pdf',
        ]));
        $verifiedNoPublic = TransporterDocument::create($this->row([
            'status' => TransporterDocument::STATUS_VERIFIED,
            'public_file_path' => null,
        ]));

        $this->assertTrue($verified->hasPublicVersion());
        $this->assertFalse($pending->hasPublicVersion());
        $this->assertFalse($verifiedNoPublic->hasPublicVersion());
    }

    /** @return array{0: Tenant, 1: TransporterDocument} */
    private function seedVerifiedTransporterWithPublicDocument(): array
    {
        $tenant = $this->makeTransporter(verification: VerificationStatus::Verified);
        $this->bindTenant($tenant);
        $path = $this->seedFileOnDisk($tenant->id);
        $id = $this->insertDocument(
            $tenant,
            status: TransporterDocument::STATUS_VERIFIED,
            publicFilePath: $path,
        );

        return [$tenant, TransporterDocument::query()->find($id)];
    }

    private function seedFileOnDisk(string $tenantId): string
    {
        $disk = Storage::disk('pub-docs-test');
        $path = 'transporter-docs/'.$tenantId.'/public/cert-'.uniqid().'.pdf';
        $disk->put($path, UploadedFile::fake()->create('cert.pdf', 30, 'application/pdf')->get());

        return $path;
    }

    private function makeTransporter(VerificationStatus $verification): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'tr-pub-'.$u,
            'name' => 'Public Doc Transporter '.$u,
            'type' => TenantType::Transporter,
            'verification_status' => $verification,
            'verified_at' => $verification === VerificationStatus::Verified ? now() : null,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function insertDocument(Tenant $tenant, string $status, ?string $publicFilePath): string
    {
        $id = (string) Str::ulid();
        $this->bindTenant($tenant);
        DB::connection('tenant')->table('transporter_documents')->insert([
            'id' => $id,
            'document_type' => TransporterDocumentType::RoadCarrierLicense->value,
            'status' => $status,
            'file_path' => 'transporter-docs/'.$tenant->id.'/orig.pdf',
            'file_size' => 50 * 1024,
            'file_mime' => 'application/pdf',
            'original_filename' => 'licencja.pdf',
            'public_file_path' => $publicFilePath,
            'public_file_size' => $publicFilePath ? 30 * 1024 : null,
            'public_file_mime' => $publicFilePath ? 'application/pdf' : null,
            'public_uploaded_at' => $publicFilePath ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function row(array $overrides): array
    {
        return array_merge([
            'id' => (string) Str::ulid(),
            'document_type' => TransporterDocumentType::RoadCarrierLicense->value,
            'status' => TransporterDocument::STATUS_VERIFIED,
            'file_path' => 'x.pdf',
            'file_size' => 1,
            'file_mime' => 'application/pdf',
            'original_filename' => 'x.pdf',
        ], $overrides);
    }

    private function bindTenant(Tenant $tenant): void
    {
        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }
}
