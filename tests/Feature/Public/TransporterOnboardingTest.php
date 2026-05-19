<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Transport\Verification\DocumentUploadService;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Mail\MasterAdmin\TransporterOnboardingSubmittedMail;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\Provisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Publiczna rejestracja transportera z dokumentami pod `/przewoznicy/dolacz`.
 * Patrz docs/TRANSPORT.md §15 + `TransporterOnboardingController`.
 *
 * Mock Provisioner (nie tworzymy faktycznie MySQL db) — focus na:
 *   - validation (NIP/REGON format, slug unique, required documents)
 *   - tenant creation z type=transporter + verification_status=pending
 *   - dokumenty zapisane do storage (z Mocked Storage disk)
 *   - email do master admin'ów
 *   - honeypot
 */
class TransporterOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(Provisioner::class, function (MockInterface $m) {
            $m->shouldReceive('makeIdentifiers')->andReturn([
                'db_name' => 'hovera_t_'.uniqid(),
                'db_user' => 'hovera_t_'.uniqid(),
            ]);
            $m->shouldReceive('generatePassword')->andReturn('PASSWORD123456789');
            $m->shouldReceive('provision')->andReturnNull();
            $m->shouldReceive('destroy')->andReturnNull();
        });

        Mail::fake();
        Storage::fake('transport-documents');
        Storage::fake('local');

        // Master admin email recipient.
        User::create([
            'email' => 'master@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);

        // DocumentUploadService writes to tenant DB — mockuje go żeby pomijał
        // tenant connection. Pełne pokrycie upload flow w
        // TransporterDocumentsServiceTest.
        $this->mock(DocumentUploadService::class, function ($m) {
            $m->shouldReceive('upload')->andReturn(new TransporterDocument([
                'id' => '01HXXXXXX',
                'status' => 'pending',
            ]));
        });
    }

    public function test_show_renders_form_with_required_documents(): void
    {
        $response = $this->get('/przewoznicy/dolacz');

        $response->assertOk();
        $response->assertSee('doc_road_carrier_license', false);
        $response->assertSee('doc_pwl_t1', false);
        $response->assertSee('doc_carrier_liability', false);
    }

    public function test_submit_creates_pending_transporter_tenant(): void
    {
        $response = $this->post('/przewoznicy/dolacz', $this->validPayload());

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/przewoznicy/dolacz/dziekujemy/', $location);

        $tenant = Tenant::query()->where('slug', 'test-firma')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(TenantType::Transporter, $tenant->type);
        $this->assertSame(VerificationStatus::Pending, $tenant->verification_status);
        $this->assertSame('1234563218', $tenant->tax_id);
        $this->assertSame('123456789', data_get($tenant->settings, 'company.regon'));
        $this->assertSame('+48 600 100 200', data_get($tenant->settings, 'contact.phone'));
        $this->assertNotNull($tenant->terms_accepted_at);
    }

    public function test_submit_sends_email_to_master_admins(): void
    {
        $this->post('/przewoznicy/dolacz', $this->validPayload());

        Mail::assertSent(TransporterOnboardingSubmittedMail::class, function ($mail) {
            return $mail->hasTo('master@hovera.app');
        });
    }

    public function test_honeypot_silent_skip_does_not_create_tenant(): void
    {
        $payload = $this->validPayload();
        $payload['website'] = 'http://spam.bot';  // bot fills honeypot

        $response = $this->post('/przewoznicy/dolacz', $payload);

        $response->assertRedirect(); // landing back to show with status
        $this->assertSame(0, Tenant::where('slug', 'test-firma')->count());
        Mail::assertNothingSent();
    }

    public function test_duplicate_slug_returns_validation_error(): void
    {
        Tenant::create([
            'slug' => 'test-firma',
            'name' => 'Existing',
            'type' => TenantType::Stable->value,
            'db_name' => 'hovera_t_existing',
            'db_username' => 'hovera_t_existing',
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $response = $this->post('/przewoznicy/dolacz', $this->validPayload());

        $response->assertSessionHasErrors('slug');
        $this->assertSame(1, Tenant::where('slug', 'test-firma')->count());
    }

    public function test_invalid_tax_id_format_returns_validation_error(): void
    {
        $payload = $this->validPayload();
        $payload['tax_id'] = '12345';  // za krótki

        $response = $this->post('/przewoznicy/dolacz', $payload);

        $response->assertSessionHasErrors('tax_id');
    }

    public function test_missing_document_returns_validation_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['doc_road_carrier_license']);

        $response = $this->post('/przewoznicy/dolacz', $payload);

        $response->assertSessionHasErrors('doc_road_carrier_license');
    }

    public function test_unaccepted_terms_returns_validation_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['terms']);

        $response = $this->post('/przewoznicy/dolacz', $payload);

        $response->assertSessionHasErrors('terms');
    }

    public function test_thanks_page_renders_for_existing_tenant(): void
    {
        $tenant = Tenant::create([
            'slug' => 'thanks-test',
            'name' => 'Thanks Test',
            'type' => TenantType::Transporter->value,
            'verification_status' => VerificationStatus::Pending->value,
            'db_name' => 'hovera_t_thanks',
            'db_username' => 'hovera_t_thanks',
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'provisioning',
        ]);

        $response = $this->get('/przewoznicy/dolacz/dziekujemy/'.$tenant->slug);

        $response->assertOk();
        $response->assertSee('Thanks Test');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Test Firma Transportowa Sp. z o.o.',
            'slug' => 'test-firma',
            'tax_id' => '1234563218',
            'regon' => '123456789',
            'address' => 'ul. Testowa 1, 00-001 Warszawa',
            'owner_name' => 'Anna Nowak',
            'owner_email' => 'anna@testfirma.pl',
            'owner_phone' => '+48 600 100 200',
            'terms' => '1',
            'doc_road_carrier_license' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            'doc_pwl_t1' => UploadedFile::fake()->create('pwl_t1.pdf', 100, 'application/pdf'),
            'doc_pwl_t2' => UploadedFile::fake()->create('pwl_t2.pdf', 100, 'application/pdf'),
            'doc_pwl_driver_handler' => UploadedFile::fake()->create('driver.pdf', 100, 'application/pdf'),
            'doc_pwl_vehicle_approval' => UploadedFile::fake()->create('vehicle.pdf', 100, 'application/pdf'),
            'doc_carrier_liability' => UploadedFile::fake()->create('oc.pdf', 100, 'application/pdf'),
        ];
    }
}
