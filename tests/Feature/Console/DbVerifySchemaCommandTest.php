<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Smoke test dla `db:verify-schema` artisan command. RefreshDatabase
 * używa sqlite (config przez tests/TestCase). Central models powinny
 * mieć migrate'owane tabele — command zwraca 0.
 *
 * Drugi test: usun jedna tabele po migrate → command zwraca 1
 * (catch'uje broken state).
 */
class DbVerifySchemaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_success_when_all_central_tables_exist(): void
    {
        $this->artisan('db:verify-schema')
            ->expectsOutputToContain('verified')
            ->assertExitCode(0);
    }

    public function test_command_exits_failure_when_a_central_table_is_missing(): void
    {
        // Drop jakiejkolwiek istniejacej central tabeli — np. plans —
        // command powinien zaraportowac brak i exit code 1.
        Schema::connection('central')->dropIfExists('plans');

        $this->artisan('db:verify-schema')
            ->expectsOutputToContain('Missing tables')
            ->assertExitCode(1);
    }
}
