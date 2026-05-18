<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\Provisioner;
use Database\Seeders\TransportPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class VerificationDataLayerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_vrf_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpDocumentsTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_transporter_signup_defaults_to_pending_verification(): void
    {
        $this->mock(Provisioner::class, function (MockInterface $m) {
            $m->shouldReceive('makeIdentifiers')->andReturn(['db_name' => 'hovera_t_test', 'db_user' => 'hovera_t_test']);
            $m->shouldReceive('generatePassword')->andReturn('PASSWORD123456789');
            $m->shouldReceive('provision')->andReturnNull();
            $m->shouldReceive('destroy')->andReturnNull();
        });
        TransportPlansSeeder::seed();
        Plan::create(['code' => 'pro', 'audience' => 'stable', 'name' => 'Pro', 'currency' => 'PLN']);

        $tenant = $this->app->make(CreateTenant::class)->execute([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => 'transporter',
        ]);

        $this->assertSame(VerificationStatus::Pending, $tenant->fresh()->verification_status);
        $this->assertFalse($tenant->fresh()->isVerifiedTransporter());
    }

    public function test_stable_tenant_has_null_verification_status(): void
    {
        $this->mock(Provisioner::class, function (MockInterface $m) {
            $m->shouldReceive('makeIdentifiers')->andReturn(['db_name' => 'hovera_t_test2', 'db_user' => 'hovera_t_test2']);
            $m->shouldReceive('generatePassword')->andReturn('PASSWORD123456789');
            $m->shouldReceive('provision')->andReturnNull();
            $m->shouldReceive('destroy')->andReturnNull();
        });
        Plan::create(['code' => 'pro', 'audience' => 'stable', 'name' => 'Pro', 'currency' => 'PLN']);

        $tenant = $this->app->make(CreateTenant::class)->execute([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia',
            // brak type → default 'stable'
        ]);

        $this->assertNull($tenant->fresh()->verification_status);
        $this->assertFalse($tenant->fresh()->isVerifiedTransporter());
    }

    public function test_is_verified_transporter_requires_both_type_and_status(): void
    {
        $tenant = new Tenant([
            'slug' => 't1', 'name' => 'T',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 'x', 'db_username' => 'x',
            'db_password_encrypted' => Crypt::encryptString('x'),
        ]);
        $this->assertTrue($tenant->isVerifiedTransporter());

        $tenant->verification_status = VerificationStatus::UnderReview;
        $this->assertFalse($tenant->isVerifiedTransporter());

        $tenant->verification_status = VerificationStatus::Verified;
        $tenant->type = TenantType::Stable;
        $this->assertFalse($tenant->isVerifiedTransporter(), 'stable type → false even when status=verified');
    }

    public function test_required_document_types_excludes_other(): void
    {
        $required = TransporterDocumentType::requiredCases();
        // Rozszerzone w PR PWL: KRS (legacy) + 7 PWL cases (Road carrier license,
        // T1, T2, Driver cert, Vehicle approval cert, Wash log, Carrier liability) = 8.
        // Legacy AnimalTransportCert / InsuranceOcp / VehicleRegistration są deprecated
        // i wyłączone z required set (zachowane jako case dla wstecznej kompatybilności DB).
        $this->assertCount(8, $required);
        $this->assertNotContains(TransporterDocumentType::Other, $required);
        $this->assertContains(TransporterDocumentType::CompanyRegistration, $required);
        $this->assertContains(TransporterDocumentType::RoadCarrierLicense, $required);
        $this->assertContains(TransporterDocumentType::CarrierLiabilityInsurance, $required);

        // Deprecated nie pojawiają się w required set.
        $this->assertNotContains(TransporterDocumentType::AnimalTransportCert, $required);
        $this->assertNotContains(TransporterDocumentType::InsuranceOcp, $required);
    }

    public function test_document_type_expires_by_law(): void
    {
        $this->assertTrue(TransporterDocumentType::AnimalTransportCert->expiresByLaw());
        $this->assertTrue(TransporterDocumentType::InsuranceOcp->expiresByLaw());
        $this->assertFalse(TransporterDocumentType::CompanyRegistration->expiresByLaw());
        $this->assertFalse(TransporterDocumentType::Other->expiresByLaw());
    }

    public function test_transporter_document_model_round_trip(): void
    {
        $doc = TransporterDocument::create([
            'id' => (string) Str::ulid(),
            'document_type' => TransporterDocumentType::AnimalTransportCert,
            'status' => TransporterDocument::STATUS_PENDING,
            'file_path' => 'transporter-docs/test/animal-cert.pdf',
            'file_size' => 1024 * 250,
            'file_mime' => 'application/pdf',
            'original_filename' => 'swiadectwo-zwierzeta.pdf',
            'expires_at' => now()->addYears(3)->toDateString(),
            'issued_at' => now()->subMonth()->toDateString(),
        ]);

        $fresh = $doc->fresh();
        $this->assertSame(TransporterDocumentType::AnimalTransportCert, $fresh->document_type);
        $this->assertSame('pending', $fresh->status);
        $this->assertFalse($fresh->isExpired());
        $this->assertFalse($fresh->isVerified());
    }

    public function test_document_is_expired_when_past_expires_at(): void
    {
        $doc = TransporterDocument::create([
            'id' => (string) Str::ulid(),
            'document_type' => TransporterDocumentType::InsuranceOcp,
            'status' => TransporterDocument::STATUS_VERIFIED,
            'file_path' => 'x.pdf',
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($doc->fresh()->isExpired());
        $this->assertFalse($doc->fresh()->isVerified(), 'verified+expired → not effectively verified');
    }

    public function test_is_expiring_soon_within_30_days(): void
    {
        $doc = TransporterDocument::create([
            'id' => (string) Str::ulid(),
            'document_type' => TransporterDocumentType::InsuranceOcp,
            'status' => TransporterDocument::STATUS_VERIFIED,
            'file_path' => 'x.pdf',
            'expires_at' => now()->addDays(15)->toDateString(),
        ]);

        $this->assertTrue($doc->isExpiringSoon());
        $this->assertFalse($doc->isExpired());
    }

    private function setUpDocumentsTable(): void
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
