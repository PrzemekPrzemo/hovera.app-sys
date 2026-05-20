<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test dla nowych sekcji `/transport` landing'u:
 *   - hero ma 3 CTA (zapytanie + konto + katalog)
 *   - sekcja "3 drogi" z linkami do każdej z paths
 *
 * Test izolowany od istniejącego `TransportLandingTest.php` żeby nie
 * mieszać scope'u (tamten testuje top-10 ranking, ten — marketplace UX).
 */
class TransportLandingMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_includes_owner_account_cta_pointing_to_registration(): void
    {
        $response = $this->get('/transport');

        $response->assertOk();
        // Hero "Załóż darmowe konto" link → /register/horse-owner
        $response->assertSee('href="'.route('register.horse-owner.show').'"', escape: false);
    }

    public function test_paths_section_lists_three_paths(): void
    {
        $response = $this->get('/transport');

        $response->assertOk();
        // 3 path titles
        $response->assertSeeText('Złóż zapytanie (broadcast)');
        $response->assertSeeText('Załóż darmowe konto właściciela');
        $response->assertSeeText('Wybierz konkretnego przewoźnika');
    }

    public function test_paths_section_each_card_has_cta_link(): void
    {
        $response = $this->get('/transport');

        $response->assertOk();
        // Owner account path → register flow
        $response->assertSee(route('register.horse-owner.show'), escape: false);
        // Directory path → /przewoznicy
        $response->assertSee(url('/przewoznicy'), escape: false);
        // Broadcast path → anchor to #inquiry
        $response->assertSee('href="#inquiry"', escape: false);
    }
}
