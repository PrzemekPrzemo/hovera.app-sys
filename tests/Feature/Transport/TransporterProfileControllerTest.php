<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportServiceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TransporterProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_verified_transporter_renders_with_name_and_cta(): void
    {
        $tenant = $this->makeTransporter('konie-trans', VerificationStatus::Verified);

        $this->get('/t/konie-trans')
            ->assertOk()
            ->assertSee($tenant->name, false)
            ->assertSee('/transport/zapytanie', false);
    }

    public function test_pending_transporter_returns_404(): void
    {
        $this->makeTransporter('niezweryfikowany', VerificationStatus::Pending);
        $this->get('/t/niezweryfikowany')->assertNotFound();
    }

    public function test_rejected_transporter_returns_404(): void
    {
        $this->makeTransporter('odrzucony', VerificationStatus::Rejected);
        $this->get('/t/odrzucony')->assertNotFound();
    }

    public function test_stable_tenant_returns_404_on_transporter_url(): void
    {
        Tenant::create([
            'slug' => 'stajnia',
            'name' => 'Stajnia Test',
            'type' => TenantType::Stable,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $this->get('/t/stajnia')->assertNotFound();
    }

    public function test_suspended_transporter_returns_404(): void
    {
        $this->makeTransporter('zawieszony', VerificationStatus::Verified, status: 'suspended');
        $this->get('/t/zawieszony')->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/t/nieistnieje')->assertNotFound();
    }

    public function test_invalid_slug_returns_404_at_route(): void
    {
        $this->get('/t/UPPER')->assertNotFound();
        $this->get('/t/-bad-')->assertNotFound();
    }

    public function test_description_from_settings_is_rendered(): void
    {
        $this->makeTransporter('z-opisem', VerificationStatus::Verified, settings: [
            'public_profile' => [
                'description' => 'Specjalizujemy się w transporcie koni sportowych.',
                'email' => 'kontakt@example.com',
                'phone' => '+48 700 800 900',
            ],
        ]);

        $this->get('/t/z-opisem')
            ->assertOk()
            ->assertSee('Specjalizujemy się w transporcie koni sportowych.', false)
            ->assertSee('kontakt@example.com', false)
            ->assertSee('+48 700 800 900', false);
    }

    public function test_branding_primary_color_is_applied(): void
    {
        $this->makeTransporter('kolorowy', VerificationStatus::Verified, branding: [
            'primary_color' => '#0ea5e9',
        ]);

        $this->get('/t/kolorowy')
            ->assertOk()
            ->assertSee('#0ea5e9', false);
    }

    public function test_service_areas_are_displayed_with_adjacency_hint(): void
    {
        $tenant = $this->makeTransporter('mazowsze', VerificationStatus::Verified);

        TransportServiceArea::query()->create([
            'transporter_tenant_id' => $tenant->id,
            'voivodeship' => 'mazowieckie',
        ]);

        $response = $this->get('/t/mazowsze');

        $response->assertOk()
            ->assertSee('mazowieckie', false)
            // Sąsiedzi mazowieckiego z config/transport.php:
            ->assertSee('łódzkie', false)
            ->assertSee('lubelskie', false);
    }

    public function test_default_primary_color_when_branding_missing(): void
    {
        $this->makeTransporter('plain', VerificationStatus::Verified);

        $this->get('/t/plain')
            ->assertOk()
            ->assertSee('#A8956B', false);
    }

    public function test_response_carries_cache_control_header(): void
    {
        $this->makeTransporter('cached', VerificationStatus::Verified);

        $cc = $this->get('/t/cached')->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=60', $cc);
        $this->assertStringContainsString('s-maxage=300', $cc);
    }

    public function test_soft_deleted_transporter_returns_404(): void
    {
        $tenant = $this->makeTransporter('usuniety', VerificationStatus::Verified);
        $tenant->delete();

        $this->get('/t/usuniety')->assertNotFound();
    }

    private function makeTransporter(
        string $slug,
        VerificationStatus $vs,
        string $status = 'active',
        array $branding = [],
        array $settings = [],
    ): Tenant {
        return Tenant::create([
            'slug' => $slug,
            'name' => 'Firma '.ucfirst(str_replace('-', ' ', $slug)),
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 't_'.str_replace('-', '_', $slug),
            'db_username' => 't_'.str_replace('-', '_', $slug),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
            'branding' => $branding ?: null,
            'settings' => $settings ?: null,
        ]);
    }
}
