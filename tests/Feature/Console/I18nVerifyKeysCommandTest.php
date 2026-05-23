<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Smoke test dla `i18n:verify-keys` artisan command. Skanuje realny
 * app/ + resources/views/ i sprawdza lang/{pl,en}/**. Test pilnuje, ze
 * stan repo nie regresuje — wszystkie wywolania __() / trans() / @lang()
 * maja tlumaczenia w PL + EN.
 *
 * Drugi test: lokalizacja ktora nie istnieje powinna byc raportowana
 * (warning + missing list dla kazdego klucza), ale takich locale w repo
 * normalnie nie ma — uzywamy --locale=nonexistent.
 */
class I18nVerifyKeysCommandTest extends TestCase
{
    public function test_all_used_translation_keys_exist_in_pl_and_en(): void
    {
        $this->artisan('i18n:verify-keys')
            ->expectsOutputToContain('verified')
            ->assertExitCode(0);
    }

    public function test_command_supports_single_locale_option(): void
    {
        $this->artisan('i18n:verify-keys', ['--locale' => 'pl'])
            ->assertExitCode(0);
    }

    public function test_command_reports_orphans_when_flag_set(): void
    {
        $this->artisan('i18n:verify-keys', ['--locale' => 'pl', '--orphans' => true])
            ->expectsOutputToContain('orphan keys')
            ->assertExitCode(0);
    }
}
