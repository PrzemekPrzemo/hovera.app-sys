<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\Provisioner;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * `php artisan hovera:tenant:dump-schema`
 *
 * Generuje `database/tenant-schema.sql` ze schematem (CREATE TABLE)
 * wszystkich tabel tenant'a + INSERT-y do `migrations` (żeby nowo
 * utworzony tenant wiedział że migracje są wgrane).
 *
 * Po co: `Provisioner::provision()` zamiast biegać przez 22+ migracji
 * Laravel'a (5+ min na slow Plesk MySQL) ładuje 1 SQL plik (~5 sec).
 * To eliminuje 504 timeout przy tworzeniu tenanta z UI.
 *
 * Workflow:
 *   1. Stwórz temp tenant DB (slug 'schema_template_<rand>')
 *   2. Migrate wszystkie tenant migracje
 *   3. Dump przez PDO (SHOW CREATE TABLE) — bez wymagania mysqldump binary
 *   4. DROP temp DB + user
 *   5. Zapisz SQL do database/tenant-schema.sql
 *
 * Uruchamiany ręcznie po deploy z nowymi tenant migracjami:
 *   php artisan hovera:tenant:dump-schema
 *
 * Albo automatycznie przez update.sh.
 */
class TenantDumpSchemaCommand extends Command
{
    protected $signature = 'hovera:tenant:dump-schema
        {--output= : Ścieżka pliku SQL (default: database/tenant-schema.sql)}';

    protected $description = 'Dump schematu tabel tenant\'a do SQL — używany potem przez Provisioner zamiast migrate per nowy tenant.';

    public function handle(Provisioner $provisioner, TenantManager $tenants): int
    {
        $output = $this->option('output') ?? base_path('database/tenant-schema.sql');

        // 1. Stwórz temp tenant
        $rand = Str::random(8);
        $slug = 'schemadump'.strtolower($rand);
        $this->info("→ Tworzę temp tenant '{$slug}'…");

        [$dbName, $dbUser] = array_values($provisioner->makeIdentifiers($slug));
        $dbPassword = $provisioner->generatePassword();

        $tempTenant = new Tenant([
            'slug' => $slug,
            'name' => 'Schema Dump Temp',
            'db_host' => config('database.connections.tenant.host'),
            'db_port' => (int) config('database.connections.tenant.port'),
            'db_name' => $dbName,
            'db_username' => $dbUser,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'status' => 'provisioning',
        ]);
        $tempTenant->db_password = $dbPassword;

        try {
            // 2. Provision + migrate — fizyczne DB, fake row (NIE save do central)
            $provisioner->provision($tempTenant);
            $this->info('→ Schema zmigrowana, dumpuję…');

            // 3. Switch context + dump przez PDO
            $tenants->setCurrent($tempTenant);
            $sql = $this->dumpSchema($tempTenant);

            // 4. Zapisz
            File::ensureDirectoryExists(dirname($output));
            File::put($output, $sql);
            $sizeKb = (int) round(strlen($sql) / 1024);
            $this->info("✓ Schema zdumpowana ({$sizeKb} KB) → {$output}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Dump schematu padł: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            // 5. Cleanup zawsze — temp DB + user
            try {
                $provisioner->destroy($tempTenant);
                $this->info("→ Cleanup '{$slug}' OK");
            } catch (Throwable $e) {
                $this->warn("  ⚠ Cleanup padł: {$e->getMessage()}. Może być potrzeba ręcznego DROP DATABASE {$dbName};");
            }
            $tenants->forget();
        }
    }

    private function dumpSchema(Tenant $tenant): string
    {
        $pdo = DB::connection('tenant')->getPdo();

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $sql = "-- Hovera tenant schema dump\n";
        $sql .= '-- Generated: '.now()->toIso8601String()."\n";
        $sql .= '-- Tables: '.count($tables)."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            if (! $row || ! isset($row['Create Table'])) {
                continue;
            }
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $row['Create Table'].";\n\n";
        }

        // Migrations — żeby nowy tenant wiedział że jest up-to-date
        $migrations = $pdo->query('SELECT migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if (count($migrations) > 0) {
            $sql .= '-- Migrations metadata ('.count($migrations)." entries)\n";
            foreach ($migrations as $m) {
                $migration = addslashes($m['migration']);
                $batch = (int) $m['batch'];
                $sql .= "INSERT INTO `migrations` (`migration`, `batch`) VALUES ('{$migration}', {$batch});\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }
}
