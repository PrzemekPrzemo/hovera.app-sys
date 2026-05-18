<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke + content tests dla stron prawnych. Krytyczna asercja: każda strona
 * publicznie linkowana w signupie + email footerach renderuje 200, a strony
 * dotyczące marketplace mają explicit pozycjonowanie Hovera = pośrednik
 * (NIE przewoźnik). To jest absolute legal requirement — gdyby ktoś usunął
 * tę sekcję z lang file, test musi czerwienić.
 */
class LegalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_renders_with_intermediary_framing(): void
    {
        $response = $this->get('/regulamin');

        $response->assertOk();
        $response->assertSee('Regulamin świadczenia usługi hovera');
        // Marketplace framing — Hovera nie jest przewoźnikiem.
        $response->assertSee('POŚREDNIK', false);
        $response->assertSee('Regulaminie marketplace transportowego', false);
        $response->assertSee('/regulamin-marketplace', false);
    }

    public function test_privacy_page_renders_with_transport_section(): void
    {
        $response = $this->get('/polityka-prywatnosci');

        $response->assertOk();
        $response->assertSee('Polityka prywatności');
        // Section 10 — marketplace data flow.
        $response->assertSee('Marketplace transportowy', false);
        $response->assertSee('NIEZALEŻNYM administratorem', false);
    }

    public function test_dpa_page_renders_with_transport_section(): void
    {
        $response = $this->get('/dpa');

        $response->assertOk();
        $response->assertSee('Umowa powierzenia');
        // Section 11 — Customer → Hovera → Transporter flow.
        $response->assertSee('Marketplace transportowy', false);
    }

    public function test_marketplace_regulamin_renders_with_full_intermediary_disclaimer(): void
    {
        $response = $this->get('/regulamin-marketplace');

        $response->assertOk();
        $response->assertSee('Regulamin marketplace transportowego', false);
        // Pierwsza zasada — Hovera nie jest przewoźnikiem (sekcja 2).
        $response->assertSee('NIE jest przewoźnikiem', false);
        $response->assertSee('POŚREDNICTWA TECHNOLOGICZNEGO', false);
        // Sekcja 6 — akceptacja oferty = umowa Klient↔Przewoźnik.
        $response->assertSee('bezpośrednią umowę przewozu', false);
        // Sekcja 8 — dwóch administratorów danych.
        $response->assertSee('dwóch administratorów', false);
        // Placeholder z configu — company name z config('hovera.legal.company_name').
        $response->assertSee(config('hovera.legal.company_name'));
    }

    public function test_marketplace_route_is_named(): void
    {
        // Bez nazwanej trasy linki w innych blade'ach (terms, privacy, signup form)
        // łamią się — sprawdź że nazwa zarejestrowana.
        $this->assertTrue(app('router')->has('legal.marketplace'));
        $url = route('legal.marketplace');
        $this->assertStringEndsWith('/regulamin-marketplace', $url);
    }

    public function test_marketplace_page_uses_legal_layout_tabs(): void
    {
        $response = $this->get('/regulamin-marketplace');

        // Tab nav: terms / privacy / dpa / marketplace. @class(['active'=>...])
        // dorzuca class atrybut, więc szukamy URL substringów (route helper
        // wstawia APP_URL prefix). Nazwy route'ów: legal.{terms,privacy,dpa}.
        $response->assertSee('/regulamin', false);
        $response->assertSee('/polityka-prywatnosci', false);
        $response->assertSee('/dpa', false);
    }
}
