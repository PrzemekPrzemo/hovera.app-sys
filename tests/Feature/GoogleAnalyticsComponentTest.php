<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Pokrywa <x-google-analytics /> Blade component — wstrzykiwany do
 * head wszystkich Filament paneli i publicznych Blade layoutów.
 *
 * Component renderuje gtag.js script tylko gdy:
 *   - config('hovera.analytics.google_id') jest non-empty
 *   - environment != 'testing' (żeby testy nie waliły do GA)
 */
class GoogleAnalyticsComponentTest extends TestCase
{
    public function test_renders_nothing_in_testing_environment(): void
    {
        // Default test env to 'testing' — snippet powinien być wyłączony
        // żeby integration testy nie raportowały do GA.
        config()->set('hovera.analytics.google_id', 'G-TESTID123');

        $rendered = Blade::render('<x-google-analytics />');

        $this->assertStringNotContainsString('googletagmanager.com', $rendered);
        $this->assertStringNotContainsString('gtag(', $rendered);
    }

    public function test_renders_nothing_when_google_id_empty(): void
    {
        // Defensive: empty string = wyłączony (deploy bez configured GA).
        $this->app['env'] = 'production';
        config()->set('hovera.analytics.google_id', '');

        $rendered = Blade::render('<x-google-analytics />');

        $this->assertStringNotContainsString('googletagmanager.com', $rendered);
    }

    public function test_renders_gtag_snippet_in_production_with_id(): void
    {
        $this->app['env'] = 'production';
        config()->set('hovera.analytics.google_id', 'G-XJXWTSLE2P');

        $rendered = Blade::render('<x-google-analytics />');

        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-XJXWTSLE2P', $rendered);
        $this->assertStringContainsString("'config'", $rendered);
        $this->assertStringContainsString('G-XJXWTSLE2P', $rendered);
        $this->assertStringContainsString('window.dataLayer', $rendered);
    }

    public function test_renders_gtag_with_custom_id_from_env(): void
    {
        $this->app['env'] = 'production';
        config()->set('hovera.analytics.google_id', 'G-CUSTOM999');

        $rendered = Blade::render('<x-google-analytics />');

        $this->assertStringContainsString('G-CUSTOM999', $rendered);
    }

    public function test_does_not_render_when_id_is_only_whitespace(): void
    {
        // trim() obronnie — '   ' nie jest validny ID.
        $this->app['env'] = 'production';
        config()->set('hovera.analytics.google_id', '   ');

        $rendered = Blade::render('<x-google-analytics />');

        $this->assertStringNotContainsString('googletagmanager.com', $rendered);
    }
}
