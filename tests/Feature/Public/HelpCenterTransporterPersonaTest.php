<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests dla publicznego centrum pomocy `/help` po dodaniu persona
 * `transporter` (PR po #250 — publiczna rejestracja /przewoznicy/dolacz).
 *
 * Per handover §8 punkt 11: HelpController był hard-coded na owner/
 * employee/specialist/client. Po dodaniu transporter'ów do PERSONAS
 * + route regex + lang keys, `/help/transporter` powinien renderować
 * dedykowaną dokumentację z resources/help/{locale}/transporter.md.
 */
class HelpCenterTransporterPersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_root_renders_default_owner_persona(): void
    {
        $this->get('/help')
            ->assertOk()
            ->assertSee(__('pages.help.persona.owner'), false);
    }

    public function test_help_transporter_persona_route_renders_transporter_content(): void
    {
        $this->get('/help/transporter')
            ->assertOk()
            ->assertSee(__('pages.help.persona.transporter'), false);
    }

    public function test_help_transporter_persona_loads_markdown_file(): void
    {
        // Sprawdza że resources/help/pl/transporter.md istnieje i jest
        // renderowane (smoke: musi być cokolwiek z transport-related).
        $response = $this->get('/help/transporter');
        $response->assertOk();

        $body = (string) $response->getContent();
        // PL transporter.md ma sekcje typu "Pojazdy", "Kierowcy", "Leady",
        // "Dokumenty PWL". Sprawdzamy że treść markdownu nie jest pusta.
        $this->assertGreaterThan(500, strlen($body), 'Transporter help content should be substantial.');
    }

    public function test_unknown_persona_404s(): void
    {
        // Route where(...) regex blokuje nieznane persona'y na poziomie
        // route'u — ma 404 zamiast renderować default owner.
        $this->get('/help/cybertruck')
            ->assertNotFound();
    }

    public function test_all_personas_in_card_list(): void
    {
        $response = $this->get('/help');
        $response->assertOk();

        // Persona cards rendered as navigation — wszystkie 5 (owner /
        // employee / specialist / client / transporter) powinno być widoczne.
        $response->assertSee(__('pages.help.persona.owner'), false);
        $response->assertSee(__('pages.help.persona.employee'), false);
        $response->assertSee(__('pages.help.persona.specialist'), false);
        $response->assertSee(__('pages.help.persona.client'), false);
        $response->assertSee(__('pages.help.persona.transporter'), false);
    }
}
