<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();   // tests share an array cache across cases
    }

    public function test_active_tenant_renders_with_basic_info(): void
    {
        $tenant = $this->makeTenant('stajnia-wisla', 'active', branding: [
            'primary_color' => '#7c3aed',
            'logo_url' => 'https://example.com/logo.png',
        ], settings: [
            'public_profile' => [
                'description' => 'Stajnia jeździecka nad Wisłą.',
                'email' => 'kontakt@stajnia-wisla.pl',
                'phone' => '+48 600 100 200',
                'address' => 'ul. Łąkowa 5, 00-001 Warszawa',
                'website' => 'https://stajnia-wisla.pl',
            ],
        ]);

        $response = $this->get('/s/stajnia-wisla');

        $response->assertOk()
            ->assertSee($tenant->name, false)
            ->assertSee('Stajnia jeździecka nad Wisłą.', false)
            ->assertSee('+48 600 100 200', false)
            ->assertSee('kontakt@stajnia-wisla.pl', false)
            ->assertSee('ul. Łąkowa 5, 00-001 Warszawa', false)
            ->assertSee('https://stajnia-wisla.pl', false)
            ->assertSee('#7c3aed', false)
            ->assertSee('https://example.com/logo.png', false);
    }

    public function test_trialing_tenant_is_visible(): void
    {
        $this->makeTenant('trial-stable', 'trialing');
        $this->get('/s/trial-stable')->assertOk();
    }

    public function test_suspended_tenant_returns_404(): void
    {
        $this->makeTenant('paused', 'suspended');
        $this->get('/s/paused')->assertNotFound();
    }

    public function test_churned_tenant_returns_404(): void
    {
        $this->makeTenant('gone', 'churned');
        $this->get('/s/gone')->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/s/does-not-exist')->assertNotFound();
    }

    public function test_invalid_slug_format_returns_404(): void
    {
        // Trailing dash is rejected at the route regex level, so we get
        // 404 from Laravel's router rather than our controller.
        $this->get('/s/-bad-')->assertNotFound();
        $this->get('/s/UPPER')->assertNotFound();
    }

    public function test_response_carries_cache_control_header(): void
    {
        $this->makeTenant('cached', 'active');

        // Symfony normalises Cache-Control directive order, so check
        // each directive is present rather than asserting an exact string.
        $cc = $this->get('/s/cached')->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=60', $cc);
        $this->assertStringContainsString('s-maxage=300', $cc);
    }

    public function test_default_primary_color_when_branding_missing(): void
    {
        $this->makeTenant('plain', 'active');   // no branding set

        $this->get('/s/plain')
            ->assertOk()
            ->assertSee('#A8956B', false);   // default Hovera ochre brand
    }

    public function test_logo_meta_is_omitted_when_missing(): void
    {
        $this->makeTenant('nologo', 'active');

        $response = $this->get('/s/nologo');
        $response->assertOk()->assertDontSee('og:image', false);
    }

    private function makeTenant(string $slug, string $status, array $branding = [], array $settings = []): Tenant
    {
        $tenant = new Tenant([
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'db_name' => 'hovera_t_'.str_replace('-', '_', $slug),
            'db_username' => 'hovera_t_'.str_replace('-', '_', $slug),
            'status' => $status,
            'branding' => $branding ?: null,
            'settings' => $settings ?: null,
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }
}
