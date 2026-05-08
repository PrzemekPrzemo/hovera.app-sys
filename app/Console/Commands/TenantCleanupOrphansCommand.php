<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * `php artisan hovera:tenant:cleanup-orphans`
 *
 * Sprząta niespójny stan między central DB a fizycznymi tenant DBs:
 *
 * Sytuacja A — DB istnieje bez central row:
 *   Z poprzedniej próby (np. migrate padł, central row został rolledback,
 *   DB nie zdążyła być dropowana). Komenda dropuje DB + user MySQL.
 *
 * Sytuacja B — central row istnieje bez DB:
 *   Ktoś usunął DB ręcznie. Komenda kasuje central row (forceDelete).
 *
 * Plus orphan invitations: zaproszenia dla nieistniejących tenant_id.
 *
 * Bezpieczne: zawsze pyta o potwierdzenie (chyba że --force).
 */
class TenantCleanupOrphansCommand extends Command
{
    protected $signature = 'hovera:tenant:cleanup-orphans
        {--force : Pomiń prompt potwierdzenia}
        {--dry-run : Tylko pokaż co by się stało, bez zmian}';

    protected $description = 'Sprząta sieroty: DB bez central tenant, central tenant bez DB, orphan invitations.';

    public function handle(): int
    {
        $this->info('🧹 Skanuję niespójne tenanty…');
        $this->newLine();

        $centralTenants = Tenant::query()
            ->withTrashed()
            ->get(['id', 'slug', 'db_name', 'db_username'])
            ->keyBy('db_name');

        // Pobierz fizyczne hovera_t_* bazy
        try {
            $pdo = DB::connection('provisioner')->getPdo();
            $physicalDbs = $pdo->query(
                "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'hovera_t_%'"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $this->error('Provisioner connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $centralDbNames = $centralTenants->keys()->all();
        $orphanDbs = array_values(array_diff($physicalDbs, $centralDbNames));
        $orphanCentral = $centralTenants->reject(fn ($t) => in_array($t->db_name, $physicalDbs, true))->values();

        if (count($orphanDbs) === 0 && $orphanCentral->isEmpty()) {
            $this->info('✓ Wszystko spójne — brak sierot.');

            return self::SUCCESS;
        }

        $this->table(['Typ', 'Co', 'Akcja'], $this->buildPreview($orphanDbs, $orphanCentral));

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — żadne zmiany nie zostały wykonane.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Wykonać te akcje?', false)) {
            $this->warn('Anulowano.');

            return self::SUCCESS;
        }

        $this->cleanup($orphanDbs, $orphanCentral, $pdo);
        $this->info('✓ Sprzątanie zakończone.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $orphanDbs
     * @param  Collection<int, Tenant>  $orphanCentral
     * @return list<array{string,string,string}>
     */
    private function buildPreview(array $orphanDbs, $orphanCentral): array
    {
        $rows = [];
        foreach ($orphanDbs as $db) {
            $guessedUser = preg_replace('/^hovera_t_/', 'hovera_t_', $db); // identyczny prefix
            $rows[] = ['DB orphan', $db, "DROP DATABASE {$db} + DROP USER '{$guessedUser}'@'%'"];
        }
        foreach ($orphanCentral as $t) {
            $rows[] = ['Central orphan', "tenant '{$t->slug}' (db: {$t->db_name})", "forceDelete + DROP USER '{$t->db_username}'@'%'"];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $orphanDbs
     * @param  Collection<int, Tenant>  $orphanCentral
     */
    private function cleanup(array $orphanDbs, $orphanCentral, PDO $pdo): void
    {
        foreach ($orphanDbs as $db) {
            $this->line("→ DROP DATABASE {$db}");
            try {
                $pdo->exec("DROP DATABASE IF EXISTS `{$db}`");
                // Zgadujemy username = nazwa db (taka konwencja w Provisioner)
                $username = $db;
                $pdo->exec("DROP USER IF EXISTS '{$username}'@'%'");
                $pdo->exec("DROP USER IF EXISTS '{$username}'@'localhost'");
            } catch (Throwable $e) {
                $this->warn("  ⚠ {$e->getMessage()}");
            }
        }

        foreach ($orphanCentral as $t) {
            $this->line("→ forceDelete tenant '{$t->slug}'");
            try {
                $pdo->exec("DROP USER IF EXISTS '{$t->db_username}'@'%'");
                $pdo->exec("DROP USER IF EXISTS '{$t->db_username}'@'localhost'");
                $t->forceDelete();
            } catch (Throwable $e) {
                $this->warn("  ⚠ {$e->getMessage()}");
            }
        }
    }
}
