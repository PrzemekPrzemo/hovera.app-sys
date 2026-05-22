<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Weryfikuje, że każdy model w app/Models/{Central,Tenant}/ ma realnie
 * istniejącą tabelę w odpowiedniej bazie (central → central connection,
 * tenant → tenant connection — wymaga ustawionego tenanta DB).
 *
 * Cel: catch'owanie sytuacji gdy ktoś usunął migrację, zrobił literówkę
 * w `$table`, lub model jest sierotą po refactorze. Wpinane w CI / deploy
 * hook.
 *
 * Użycie:
 *   php artisan db:verify-schema              — sprawdza tylko central
 *   php artisan db:verify-schema --tenant=ULID — sprawdza central + tenant
 *
 * Exit codes:
 *   0 — wszystkie tabele istnieją
 *   1 — brakuje 1+ tabel (lista wypisana, CI sypie)
 */
class DbVerifySchemaCommand extends Command
{
    protected $signature = 'db:verify-schema {--tenant= : Optional tenant ULID — also check that tenant DB has all tenant model tables}';

    protected $description = 'Verify every Eloquent model has its DB table present (catches dropped migrations / typos).';

    public function handle(): int
    {
        $centralResults = $this->verifyDirectory(
            modelsDir: 'Central',
            label: 'central',
            connection: 'central',
        );

        $tenantResults = [];
        $tenantArg = $this->option('tenant');
        if ($tenantArg !== null) {
            // Załaduj tenanta + przepnij connection — używa istniejącego
            // TenantManager żeby nie duplikować routing'u DB.
            $tenant = Tenant::query()->find($tenantArg);
            if ($tenant === null) {
                $this->error("Tenant '{$tenantArg}' not found in central DB.");

                return self::FAILURE;
            }

            try {
                app(TenantManager::class)->setCurrent($tenant);
            } catch (Throwable $e) {
                $this->error('Failed to switch tenant connection: '.$e->getMessage());

                return self::FAILURE;
            }

            $tenantResults = $this->verifyDirectory(
                modelsDir: 'Tenant',
                label: 'tenant ('.$tenant->slug.')',
                connection: 'tenant',
            );
        } else {
            $this->line('  · Skipped tenant check (no --tenant=ULID). Use --tenant=<id> to verify tenant DB too.');
        }

        $missing = array_merge(
            array_filter($centralResults, fn ($r) => $r['exists'] === false),
            array_filter($tenantResults, fn ($r) => $r['exists'] === false),
        );

        if ($missing === []) {
            $count = count($centralResults) + count($tenantResults);
            $this->info("✓ All {$count} model→table mappings verified.");

            return self::SUCCESS;
        }

        $this->error('✗ Missing tables for '.count($missing).' models:');
        $this->table(
            ['Model', 'Expected table', 'Connection'],
            array_map(fn ($r) => [$r['model'], $r['table'], $r['connection']], $missing),
        );

        return self::FAILURE;
    }

    /**
     * @return list<array{model:string, table:string, connection:string, exists:bool}>
     */
    private function verifyDirectory(string $modelsDir, string $label, string $connection): array
    {
        $path = app_path('Models/'.$modelsDir);
        if (! is_dir($path)) {
            $this->warn("Directory not found: {$path}");

            return [];
        }

        $this->info("Verifying {$label} models in app/Models/{$modelsDir}/ ...");

        $results = [];
        foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
            $relative = str_replace([app_path().'/', '.php', '/'], ['', '', '\\'], $file->getRealPath());
            $class = 'App\\'.$relative;

            try {
                if (! class_exists($class)) {
                    continue;
                }
                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }
                /** @var Model $instance */
                $instance = $reflection->newInstanceWithoutConstructor();
                $table = $instance->getTable();
                $exists = Schema::connection($connection)->hasTable($table);

                $results[] = [
                    'model' => $class,
                    'table' => $table,
                    'connection' => $connection,
                    'exists' => $exists,
                ];

                if (! $exists) {
                    $this->line("  ✗ {$class} → {$table} (NOT FOUND)");
                }
            } catch (Throwable $e) {
                // Soft-fail per model — jakikolwiek introspection error
                // (np. brak konstruktora) nie crashuje całej weryfikacji.
                $this->warn("  ? {$class} — skipped (".$e->getMessage().')');
            }
        }

        return $results;
    }
}
