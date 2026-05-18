<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SitemapControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_sitemap_renders_xml_with_correct_content_type(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
        $response->assertSee('<loc>'.url('/transport/zapytanie').'</loc>', false);
        $response->assertSee('<loc>'.url('/transport/calculator').'</loc>', false);
    }

    public function test_sitemap_includes_verified_transporters(): void
    {
        $this->makeTransporter('konie-trans', VerificationStatus::Verified);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/t/konie-trans').'</loc>', false);
    }

    public function test_sitemap_excludes_pending_transporters(): void
    {
        $this->makeTransporter('niezweryfikowany', VerificationStatus::Pending);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/t/niezweryfikowany', false);
    }

    public function test_sitemap_excludes_rejected_transporters(): void
    {
        $this->makeTransporter('odrzucony', VerificationStatus::Rejected);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/t/odrzucony', false);
    }

    public function test_sitemap_excludes_suspended_tenants(): void
    {
        $this->makeTransporter('zawieszony', VerificationStatus::Verified, status: 'suspended');
        $this->makeStable('zawieszona-stajnia', status: 'suspended');

        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertDontSee('/t/zawieszony', false);
        $response->assertDontSee('/s/zawieszona-stajnia', false);
    }

    public function test_sitemap_includes_active_stables(): void
    {
        $this->makeStable('aktywna-stajnia');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/s/aktywna-stajnia').'</loc>', false);
    }

    public function test_sitemap_excludes_static_panels_and_tokens(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertDontSee('/admin', false);
        $response->assertDontSee('/transport/quote', false);
        $response->assertDontSee('/invite/', false);
    }

    public function test_sitemap_caches_response(): void
    {
        $cc = $this->get('/sitemap.xml')->headers->get('Cache-Control');

        $this->assertStringContainsString('public', (string) $cc);
        $this->assertStringContainsString('max-age=3600', (string) $cc);
    }

    public function test_robots_txt_renders_with_correct_content_type(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /transport/quote/', false);
    }

    public function test_robots_txt_points_to_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    private function makeTransporter(
        string $slug,
        VerificationStatus $vs,
        string $status = 'active',
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
        ]);
    }

    private function makeStable(string $slug, string $status = 'active'): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => 'Stajnia '.ucfirst(str_replace('-', ' ', $slug)),
            'type' => TenantType::Stable,
            'db_name' => 's_'.str_replace('-', '_', $slug),
            'db_username' => 's_'.str_replace('-', '_', $slug),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
        ]);
    }
}
