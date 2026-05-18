<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Documents;

use App\Domain\Transport\Verification\VerificationChecklistService;
use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerificationChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_chk_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpDocsTable();
        $this->tenant = $this->makeTenant(VerificationStatus::Pending);

        $held = $this->tenant;
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
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_empty_db_yields_full_missing_checklist(): void
    {
        $checklist = app(VerificationChecklistService::class)->build();

        $this->assertSame(0, $checklist->verifiedCount);
        // 7 slotów: KRS + Road + (T1/T2) + Driver + Vehicle + Wash + OC
        $this->assertSame(7, $checklist->totalRequired);
        $this->assertFalse($checklist->isComplete());
        $this->assertCount(7, $checklist->missingLabels);
    }

    public function test_t1_or_t2_uses_alternative_slot(): void
    {
        // Wgrywamy tylko T2 (pending). Slot "PWL authorization alternative"
        // powinien być pending (nie verified).
        $this->makeDoc(TransporterDocumentType::PwlAuthorizationT2, TransporterDocument::STATUS_PENDING);

        $checklist = app(VerificationChecklistService::class)->build();
        $authSlot = collect($checklist->items)->first(
            fn ($i) => $i->label === __('transport/documents.checklist.pwl_authorization_alternative')
        );

        $this->assertNotNull($authSlot);
        $this->assertSame('pending', $authSlot->status);
    }

    public function test_t1_verified_satisfies_alternative_slot(): void
    {
        $this->makeDoc(TransporterDocumentType::PwlAuthorizationT1, TransporterDocument::STATUS_VERIFIED);

        $checklist = app(VerificationChecklistService::class)->build();
        $authSlot = collect($checklist->items)->first(
            fn ($i) => $i->label === __('transport/documents.checklist.pwl_authorization_alternative')
        );

        $this->assertTrue($authSlot->isVerified());
    }

    public function test_all_required_verified_marks_complete(): void
    {
        $required = [
            TransporterDocumentType::CompanyRegistration,
            TransporterDocumentType::RoadCarrierLicense,
            TransporterDocumentType::PwlAuthorizationT2, // alternatywa — T2 wystarcza
            TransporterDocumentType::PwlDriverHandlerCertificate,
            TransporterDocumentType::PwlVehicleApprovalCertificate,
            TransporterDocumentType::WashDisinfectionLog,
            TransporterDocumentType::CarrierLiabilityInsurance,
        ];

        foreach ($required as $type) {
            $this->makeDoc($type, TransporterDocument::STATUS_VERIFIED);
        }

        $checklist = app(VerificationChecklistService::class)->build();

        $this->assertSame(7, $checklist->verifiedCount);
        $this->assertSame(7, $checklist->totalRequired);
        $this->assertTrue($checklist->isComplete());
        $this->assertSame([], $checklist->missingLabels);
    }

    public function test_one_missing_breaks_complete(): void
    {
        $required = [
            TransporterDocumentType::CompanyRegistration,
            TransporterDocumentType::RoadCarrierLicense,
            TransporterDocumentType::PwlAuthorizationT1,
            TransporterDocumentType::PwlDriverHandlerCertificate,
            TransporterDocumentType::PwlVehicleApprovalCertificate,
            TransporterDocumentType::WashDisinfectionLog,
            // OC celowo pomijamy
        ];

        foreach ($required as $type) {
            $this->makeDoc($type, TransporterDocument::STATUS_VERIFIED);
        }

        $checklist = app(VerificationChecklistService::class)->build();
        $this->assertFalse($checklist->isComplete());
        $this->assertCount(1, $checklist->missingLabels);
    }

    private function makeDoc(TransporterDocumentType $type, string $status): TransporterDocument
    {
        return TransporterDocument::create([
            'id' => (string) Str::ulid(),
            'document_type' => $type,
            'status' => $status,
            'file_path' => 'x/'.$type->value.'.pdf',
        ]);
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
            $t->timestamp('expiry_notified_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
