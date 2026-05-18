<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransporterOgImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('public');
    }

    public function test_renders_png_for_verified_transporter(): void
    {
        $this->makeTransporter('og-firma', VerificationStatus::Verified);

        $response = $this->get('/t/og-firma/og-image.png');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $bytes = (string) $response->getContent();
        $this->assertGreaterThan(0, strlen((string) $bytes));
        $this->assertStringStartsWith("\x89PNG", (string) $bytes);
    }

    public function test_image_has_correct_dimensions_1200x630(): void
    {
        $this->makeTransporter('og-wymiary', VerificationStatus::Verified);

        $response = $this->get('/t/og-wymiary/og-image.png');
        $response->assertOk();

        $bytes = (string) $response->getContent();
        $img = imagecreatefromstring((string) $bytes);
        $this->assertNotFalse($img);
        $this->assertSame(1200, imagesx($img));
        $this->assertSame(630, imagesy($img));
        imagedestroy($img);
    }

    public function test_404_for_unverified_transporter(): void
    {
        $this->makeTransporter('og-pending', VerificationStatus::Pending);
        $this->get('/t/og-pending/og-image.png')->assertNotFound();

        $this->makeTransporter('og-rejected', VerificationStatus::Rejected);
        $this->get('/t/og-rejected/og-image.png')->assertNotFound();
    }

    public function test_404_for_stable_tenant(): void
    {
        Tenant::create([
            'slug' => 'og-stajnia',
            'name' => 'Stajnia OG',
            'type' => TenantType::Stable,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $this->get('/t/og-stajnia/og-image.png')->assertNotFound();
    }

    public function test_404_for_unknown_slug(): void
    {
        $this->get('/t/og-nieistnieje/og-image.png')->assertNotFound();
    }

    public function test_404_for_suspended_transporter(): void
    {
        $this->makeTransporter('og-zawieszony', VerificationStatus::Verified, status: 'suspended');
        $this->get('/t/og-zawieszony/og-image.png')->assertNotFound();
    }

    public function test_404_for_soft_deleted_transporter(): void
    {
        $tenant = $this->makeTransporter('og-usuniety', VerificationStatus::Verified);
        $tenant->delete();
        $this->get('/t/og-usuniety/og-image.png')->assertNotFound();
    }

    public function test_cache_control_header_set(): void
    {
        $this->makeTransporter('og-cache', VerificationStatus::Verified);

        $cc = $this->get('/t/og-cache/og-image.png')->headers->get('Cache-Control');
        $this->assertNotNull($cc);
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=86400', $cc);
        $this->assertStringContainsString('s-maxage=604800', $cc);
        $this->assertStringContainsString('immutable', $cc);
    }

    public function test_image_is_cached_on_disk_after_first_render(): void
    {
        $this->makeTransporter('og-na-dysku', VerificationStatus::Verified);

        $this->get('/t/og-na-dysku/og-image.png')->assertOk();

        Storage::disk('public')->assertExists('og-images/transporter/og-na-dysku.png');
    }

    public function test_meta_tag_in_profile_view_links_to_og_image_route(): void
    {
        $this->makeTransporter('og-meta', VerificationStatus::Verified);

        $response = $this->get('/t/og-meta');
        $response->assertOk();

        $expectedUrl = route('public.transporter.og_image', ['slug' => 'og-meta'], absolute: false);
        $response->assertSee($expectedUrl, false);
        $response->assertSee('og:image:width', false);
        $response->assertSee('1200', false);
        $response->assertSee('630', false);
        $response->assertSee('summary_large_image', false);
    }

    public function test_render_uses_tenant_primary_color_when_valid(): void
    {
        $this->makeTransporter('og-color', VerificationStatus::Verified, branding: [
            'primary_color' => '#0ea5e9',
        ]);

        // Sanity: response renders and is PNG even with custom color.
        $response = $this->get('/t/og-color/og-image.png');
        $response->assertOk();

        $bytes = (string) $response->getContent();
        $img = imagecreatefromstring((string) $bytes);
        $this->assertNotFalse($img);

        // Sample a pixel near top-left padding — should have non-zero blue
        // component because background uses #0ea5e9 with gradient overlay.
        $colorIndex = imagecolorat($img, 100, 100);
        $blue = $colorIndex & 0xFF;
        $this->assertGreaterThan(100, $blue, 'Background blue channel should reflect primary_color');
        imagedestroy($img);
    }

    public function test_render_uses_tagline_from_settings(): void
    {
        // Smoke-test: tagline ze settings nie crashuje renderowania.
        $this->makeTransporter('og-tagline', VerificationStatus::Verified, settings: [
            'public_profile' => [
                'tagline' => 'Tu wiozą konie sportowe z klasą',
            ],
        ]);

        $this->get('/t/og-tagline/og-image.png')->assertOk();
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
