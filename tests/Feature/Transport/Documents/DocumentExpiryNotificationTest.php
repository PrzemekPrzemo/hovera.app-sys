<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Documents;

use App\Domain\Transport\Notifications\TransporterDocumentExpiringSoonNotification;
use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentExpiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_expnot_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpDocsTable();
        $this->tenant = $this->makeTenantWithOwner('owner@example.com');

        // TenantManager — execute() musi działać tak żeby wewnętrzny callback
        // miał current=$tenant. Tutaj sqlite jest już skonfigurowane jako 'tenant'
        // connection — wystarczy żeby setCurrent nie próbowało rekonfigurować.
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
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_command_sends_notification_within_30_day_window(): void
    {
        Notification::fake();

        // Verified dok wygasający za 10 dni — IN.
        $this->makeDoc(TransporterDocumentType::CarrierLiabilityInsurance, now()->addDays(10));

        $this->artisan('transporter:docs-expiry-notify')->assertSuccessful();

        Notification::assertSentOnDemand(TransporterDocumentExpiringSoonNotification::class);
    }

    public function test_command_skips_document_outside_window(): void
    {
        Notification::fake();

        // Verified dok wygasający za 60 dni — POZA oknem.
        $this->makeDoc(TransporterDocumentType::CarrierLiabilityInsurance, now()->addDays(60));

        $this->artisan('transporter:docs-expiry-notify')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_is_idempotent_for_same_run(): void
    {
        Notification::fake();
        $doc = $this->makeDoc(TransporterDocumentType::WashDisinfectionLog, now()->addDays(20));

        $this->artisan('transporter:docs-expiry-notify')->assertSuccessful();
        $this->artisan('transporter:docs-expiry-notify')->assertSuccessful();

        // expiry_notified_at zapisane po pierwszym przebiegu — drugi run nie wysyła.
        Notification::assertSentOnDemandTimes(TransporterDocumentExpiringSoonNotification::class, 1);
        $this->assertNotNull($doc->fresh()->expiry_notified_at);
    }

    public function test_command_skips_pending_documents(): void
    {
        Notification::fake();
        $this->makeDoc(
            TransporterDocumentType::RoadCarrierLicense,
            now()->addDays(10),
            TransporterDocument::STATUS_PENDING,
        );

        $this->artisan('transporter:docs-expiry-notify')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_command_skips_legacy_only_document_types(): void
    {
        Notification::fake();
        // Legacy `InsuranceOcp` — NIE jest w pwlRequiredCases, nie wysyłamy notify.
        $this->makeDoc(TransporterDocumentType::InsuranceOcp, now()->addDays(10));

        $this->artisan('transporter:docs-expiry-notify')->assertSuccessful();
        Notification::assertNothingSent();
    }

    private function makeDoc(
        TransporterDocumentType $type,
        Carbon $expiresAt,
        string $status = TransporterDocument::STATUS_VERIFIED,
    ): TransporterDocument {
        return TransporterDocument::create([
            'id' => (string) Str::ulid(),
            'document_type' => $type,
            'status' => $status,
            'file_path' => 'x/'.$type->value.'.pdf',
            'expires_at' => $expiresAt->toDateString(),
        ]);
    }

    private function makeTenantWithOwner(string $email): Tenant
    {
        $tenant = Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Test Transporter',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        $user = User::create([
            'email' => $email,
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return $tenant;
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
