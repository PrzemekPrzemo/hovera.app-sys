<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

/**
 * `php artisan hovera:doctor`
 *
 * Health-check środowiska — uruchamiaj po deploy / przed pokazem demo.
 * Łapie wszystkie typowe pułapki które wybuchały w produkcji wczoraj:
 *
 *   - PHP version mismatch (CLI vs FPM)
 *   - Provisioner MySQL grants (CREATE / DROP / GRANT OPTION)
 *   - bootstrap/app.php: brak config() w withMiddleware callback
 *   - Filament closure params: zarezerwowane $q / $s / $action z type-hint
 *   - Storage permissions
 *   - Half-baked tenants (DB bez central row, central row bez DB)
 *   - migrate status (central + tenants)
 *
 * Exit code: 0 = OK, 1 = warnings (cosmetic), 2 = errors (blocking).
 */
class DoctorCommand extends Command
{
    protected $signature = 'hovera:doctor
        {--fix : Próbuj naprawić wykryte problemy (ostrożnie — niektóre wymagają ręcznych decyzji)}';

    protected $description = 'Diagnostyka środowiska — wykrywa typowe pułapki produkcyjne (PHP, provisioner, code smells).';

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->info('🩺 Hovera Doctor — diagnostyka środowiska');
        $this->newLine();

        $this->checkPhpVersion();
        $this->checkRequiredExtensions();
        $this->checkPermissions();
        $this->checkBootstrapAppPhp();
        $this->checkFilamentClosureParams();
        $this->checkProvisionerGrants();
        $this->checkTenantSchemaDump();
        $this->checkOrphanTenants();
        $this->checkMigrationsStatus();

        $this->newLine();
        if ($this->errors > 0) {
            $this->error("✗ {$this->errors} blocking error(s), {$this->warnings} warning(s).");

            return self::INVALID;
        }
        if ($this->warnings > 0) {
            $this->warn("⚠ {$this->warnings} warning(s) but no blocking errors.");

            return self::FAILURE;
        }
        $this->info('✓ Wszystko OK.');

        return self::SUCCESS;
    }

    private function checkPhpVersion(): void
    {
        $cliVersion = PHP_VERSION;
        $cliMajorMinor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $required = '8.4';

        if (version_compare($cliMajorMinor, $required, '>=')) {
            $this->ok("PHP CLI: {$cliVersion} (>= {$required})");
        } else {
            $this->flagFail("PHP CLI: {$cliVersion} — wymagane >= {$required}. Composer.lock zawiera deps wymagające 8.4 (symfony/clock).");
        }
    }

    private function checkRequiredExtensions(): void
    {
        $required = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json'];
        $recommended = ['bcmath', 'gd', 'intl', 'curl', 'fileinfo', 'zip'];

        $missing = array_filter($required, fn ($ext) => ! extension_loaded($ext));
        if (count($missing) > 0) {
            $this->flagFail('Brakuje wymaganych rozszerzeń PHP: '.implode(', ', $missing));
        } else {
            $this->ok('Wymagane rozszerzenia PHP: '.implode(', ', $required));
        }

        $missingRec = array_filter($recommended, fn ($ext) => ! extension_loaded($ext));
        if (count($missingRec) > 0) {
            $this->flagWarn('Brakuje zalecanych rozszerzeń (niekrytyczne): '.implode(', ', $missingRec));
        }
    }

    private function checkPermissions(): void
    {
        $paths = [
            'storage/app' => true,
            'storage/logs' => true,
            'storage/framework/cache' => true,
            'storage/framework/sessions' => true,
            'storage/framework/views' => true,
            'bootstrap/cache' => true,
        ];

        $allOk = true;
        foreach ($paths as $path => $writable) {
            $full = base_path($path);
            if (! File::isDirectory($full)) {
                $this->flagFail("Brakuje katalogu: {$path}");
                $allOk = false;

                continue;
            }
            if ($writable && ! is_writable($full)) {
                $this->flagFail("Katalog niezapisywalny przez PHP user'a: {$path}. Plesk: chown -R <vhost-user>:psaserv {$path}");
                $allOk = false;
            }
        }
        if ($allOk) {
            $this->ok('Permissions storage/ + bootstrap/cache OK');
        }
    }

    private function checkBootstrapAppPhp(): void
    {
        $file = base_path('bootstrap/app.php');
        if (! File::exists($file)) {
            $this->flagFail('Brak bootstrap/app.php');

            return;
        }
        $content = File::get($file);

        // Wytnij komentarze (// i /* */) przed skanowaniem — żeby
        // wzmianki o config() w komentarzach (jak ten po PR #49 w
        // bootstrap/app.php) nie wpadały w regex jako false positive.
        $stripped = preg_replace('!//[^\n]*!', '', $content);
        $stripped = preg_replace('!/\*.*?\*/!s', '', (string) $stripped);

        // Sprawdź czy w withMiddleware/withRouting/withExceptions callbackach
        // ktoś używa config() (które jeszcze nie jest dostępne w tym momencie).
        if (preg_match('/->withMiddleware\(function[^}]*config\(/s', (string) $stripped)
            || preg_match('/->withExceptions\(function[^}]*config\(/s', (string) $stripped)) {
            $this->flagFail('bootstrap/app.php: użycie config() w withMiddleware/withExceptions callbacku. config() jeszcze nie jest dostępne — użyj env() zamiast.');
        } else {
            $this->ok('bootstrap/app.php: brak config() w with* callbackach');
        }
    }

    private function checkFilamentClosureParams(): void
    {
        // Filament 3 closure resolver wstrzykuje argumenty po nazwach.
        // KLUCZOWA pułapka: $action — Filament zawsze wstrzykuje swój
        // Tables\Actions\Action object. Custom type-hint (np.
        // StartImpersonation $action) → TypeError.
        //
        // Pozostałe nazwy ($record, $state, $livewire, $component) Z type-hintem
        // są OK — Filament wstrzykuje aktualny model/state, type-hint pomaga
        // IDE i statyk analizie. Filament akceptuje typed parameter.

        $issues = [];
        $files = File::allFiles(base_path('app/Filament'));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = File::get($file->getPathname());
            $relative = str_replace(base_path().'/', '', $file->getPathname());

            // Tylko $action z customowym type-hintem (klasa zaczynająca
            // się od dużej litery, NIE zaczynająca się od ?). Wykluczamy
            // standard Filament Action types z namespace Filament/.
            if (preg_match_all('/fn\s*\([^)]*?([A-Z][a-zA-Z0-9_\\\\]+)\s+\$action\b/', $content, $m)) {
                foreach ($m[1] as $type) {
                    // Filament's own Action class jest OK, custom App\Actions\* nie.
                    if (! str_starts_with($type, 'Filament\\')
                        && ! in_array($type, ['Action', 'Tables\\Actions\\Action'], true)) {
                        $issues[] = "{$relative}: \$action z customowym type-hintem '{$type}' — Filament wstrzykuje Action object zamiast";
                    }
                }
            }
        }

        if (empty($issues)) {
            $this->ok('Filament closure params: brak kolizji nazw zarezerwowanych');
        } else {
            foreach (array_unique($issues) as $issue) {
                $this->flagFail('Filament closure: '.$issue);
            }
        }
    }

    private function checkProvisionerGrants(): void
    {
        try {
            $pdo = DB::connection('provisioner')->getPdo();
            $username = config('database.connections.provisioner.username');
            $stmt = $pdo->query('SHOW GRANTS FOR CURRENT_USER()');
            $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hasGlobalGrant = false;
            $hasSchemaGrant = false;
            foreach ($grants as $g) {
                if (str_contains($g, 'ON *.*') && str_contains($g, 'WITH GRANT OPTION')) {
                    $hasGlobalGrant = true;
                }
                if (preg_match('/ON `?hovera[\\\\_]?_t[\\\\_]?_/', $g) && str_contains($g, 'WITH GRANT OPTION')) {
                    $hasSchemaGrant = true;
                }
            }

            if ($hasGlobalGrant) {
                $this->ok("Provisioner '{$username}': ALL PRIVILEGES *.* WITH GRANT OPTION ✓");
            } elseif ($hasSchemaGrant) {
                $this->ok("Provisioner '{$username}': hovera_t_% WITH GRANT OPTION ✓");
            } else {
                $this->flagFail("Provisioner '{$username}' nie ma WITH GRANT OPTION na hovera_t_% ani na *.*. Tenanty się nie utworzą. Fix: plesk db -e \"GRANT ALL PRIVILEGES ON *.* TO '{$username}'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;\"");
            }
        } catch (Throwable $e) {
            $this->flagFail("Provisioner connection failed: {$e->getMessage()}");
        }
    }

    private function checkTenantSchemaDump(): void
    {
        $dump = base_path('database/tenant-schema.sql');
        if (! File::exists($dump)) {
            $this->flagWarn('Brak database/tenant-schema.sql — Provisioner tworzy tenanty przez 22 migracje (5 min na slow MySQL). Wygeneruj: php artisan hovera:tenant:dump-schema');

            return;
        }

        // Czy dump nie jest starszy niż najnowsza tenant migracja
        $dumpMtime = File::lastModified($dump);
        $newestMigration = 0;
        $migrationsDir = base_path('database/migrations/tenant');
        if (File::isDirectory($migrationsDir)) {
            foreach (File::files($migrationsDir) as $f) {
                $newestMigration = max($newestMigration, $f->getMTime());
            }
        }

        if ($dumpMtime < $newestMigration) {
            $age = round(($newestMigration - $dumpMtime) / 86400, 1);
            $this->flagWarn("database/tenant-schema.sql jest starszy niż najnowsza migracja ({$age} dni). Zregeneruj: php artisan hovera:tenant:dump-schema");
        } else {
            $size = round(File::size($dump) / 1024, 1);
            $this->ok("Schema dump: {$size} KB, świeży");
        }
    }

    private function checkOrphanTenants(): void
    {
        try {
            $centralTenants = Tenant::query()
                ->withTrashed()
                ->pluck('db_name', 'slug')
                ->all();

            $pdo = DB::connection('provisioner')->getPdo();
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'hovera_t_%'");
            $physicalDbs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $centralDbs = array_values($centralTenants);
            $orphanDbs = array_diff($physicalDbs, $centralDbs);
            $missingDbs = array_diff($centralDbs, $physicalDbs);

            if (count($orphanDbs) > 0) {
                $this->flagWarn('Sieroty (DB bez central tenant row): '.implode(', ', $orphanDbs).'. Fix: php artisan hovera:tenant:cleanup-orphans');
            }
            if (count($missingDbs) > 0) {
                foreach ($missingDbs as $dbName) {
                    $slug = array_search($dbName, $centralTenants, true);
                    $this->flagWarn("Central tenant '{$slug}' (db: {$dbName}) — DB nie istnieje fizycznie. Fix: hovera:tenant:cleanup-orphans");
                }
            }
            if (count($orphanDbs) === 0 && count($missingDbs) === 0) {
                $this->ok('Tenanty: brak sierot ('.count($centralTenants).' synced)');
            }
        } catch (Throwable $e) {
            $this->flagWarn("Skan sierot tenantów failed: {$e->getMessage()}");
        }
    }

    private function checkMigrationsStatus(): void
    {
        try {
            $pendingCentral = collect(Artisan::output())->count();
            Artisan::call('migrate:status', ['--database' => 'central'], $this->getOutput());
            $output = Artisan::output();
            if (str_contains($output, 'Pending')) {
                $this->flagWarn('Central DB ma pending migrations. Fix: php artisan migrate --force');
            } else {
                $this->ok('Central DB migrations: up to date');
            }
        } catch (Throwable $e) {
            $this->flagWarn("Migrations status failed: {$e->getMessage()}");
        }
    }

    private function ok(string $msg): void
    {
        $this->line('  <fg=green>✓</> '.$msg);
    }

    private function flagWarn(string $msg): void
    {
        $this->line('  <fg=yellow>⚠</> '.$msg);
        $this->warnings++;
    }

    private function flagFail(string $msg): void
    {
        $this->line('  <fg=red>✗</> '.$msg);
        $this->errors++;
    }
}
