<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Dump'uje wszystkie dane tenant'a do JSON (po jednym pliku per model)
 * + manifest z meta. Cel:
 *  - GDPR data portability (klient prosi o eksport swoich danych)
 *  - backup przed ryzykowną operacją (migracja, ręczna naprawa)
 *  - support / debug (snapshot stanu konkretnego stable'a)
 *
 * Pliki lądują w `storage/app/exports/tenant-{slug}-{Y-m-d_His}/`:
 *   _manifest.json         — meta + lista plików + liczby
 *   tenant.json            — central row (settings, plan, status, …)
 *   {table_name}.json      — pełna zawartość tabeli (UTF-8, pretty print)
 *
 * Limitacje świadome:
 *  - dump in-memory per model (load all → encode → write). Dla tenant'ów
 *    > kilkuset MB zaleca się chunkowanie — przy obecnej skali (~tysiące
 *    rekordów / tenant) overkill.
 *  - pliki binarne (HorseDocument, HorsePhoto) zrzucane są tylko jako
 *    metadane DB. Faktyczne pliki w S3/local nie są kopiowane.
 *  - IdempotencyKey / SyncVersionCounter pomijane — technical, bez
 *    wartości user-facing po restore.
 *
 * Użycie:
 *   php artisan tenant:export {ulid}
 *   php artisan tenant:export {ulid} --out=/tmp/backup
 */
class TenantExportCommand extends Command
{
    protected $signature = 'tenant:export
        {ulid : Tenant ULID (central tenants.id)}
        {--out= : Output directory (default: storage/app/exports)}';

    protected $description = 'Export all data for a tenant as JSON (GDPR portability / backup).';

    private const SKIP_MODELS = [
        'App\\Models\\Tenant\\IdempotencyKey',
        'App\\Models\\Tenant\\SyncVersionCounter',
    ];

    public function handle(TenantManager $tenants): int
    {
        $ulid = (string) $this->argument('ulid');
        $tenant = Tenant::query()->find($ulid);
        if ($tenant === null) {
            $this->error("Tenant '{$ulid}' not found in central DB.");

            return self::FAILURE;
        }

        $outBase = (string) ($this->option('out') ?? storage_path('app/exports'));
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $dir = rtrim($outBase, '/').'/tenant-'.$tenant->slug.'-'.$timestamp;

        if (! is_dir($dir) && ! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            $this->error("Failed to create output directory: {$dir}");

            return self::FAILURE;
        }

        $this->info("Exporting tenant '{$tenant->slug}' ({$tenant->id}) to {$dir}");

        // Central row najpierw — bez switcha connection.
        $this->writeJson($dir.'/tenant.json', $tenant->toArray());

        // Skip-if-same-tenant pattern (matches SnapshotTenantHealthCommand)
        // — pozwala uruchomić komendę w już aktywnym kontekście (testy,
        // queue worker który pre-resolvował tenancy).
        if ($tenants->current()?->id !== $tenant->id) {
            try {
                $tenants->setCurrent($tenant);
            } catch (Throwable $e) {
                $this->error('Failed to switch tenant connection: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $models = $this->discoverTenantModels();
        $manifest = [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_type' => $tenant->type?->value,
            'exported_at' => Carbon::now()->toIso8601String(),
            'app_version' => config('app.version', 'unknown'),
            'tables' => [],
        ];

        $totalRows = 0;
        foreach ($models as $class) {
            $table = $this->safeTable($class);
            if ($table === null) {
                $manifest['tables'][] = ['model' => $class, 'status' => 'skipped'];

                continue;
            }

            try {
                /** @var class-string<Model> $class */
                $rows = $class::query()->get()->toArray();
                $this->writeJson($dir.'/'.$table.'.json', $rows);
                $manifest['tables'][] = [
                    'model' => $class,
                    'table' => $table,
                    'rows' => count($rows),
                    'file' => $table.'.json',
                ];
                $totalRows += count($rows);
                $this->line("  → {$table}: ".count($rows).' rows');
            } catch (Throwable $e) {
                $this->warn("  ? {$table} — skipped (".$e->getMessage().')');
                $manifest['tables'][] = [
                    'model' => $class,
                    'table' => $table,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $manifest['total_rows'] = $totalRows;
        $this->writeJson($dir.'/_manifest.json', $manifest);

        $this->info("✓ Exported {$totalRows} rows across ".count($models).' models.');
        $this->info("✓ Output: {$dir}");

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function discoverTenantModels(): array
    {
        $models = [];
        $path = app_path('Models/Tenant');
        foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
            $relative = str_replace([app_path().'/', '.php', '/'], ['', '', '\\'], $file->getRealPath());
            $class = 'App\\'.$relative;
            if (in_array($class, self::SKIP_MODELS, true)) {
                continue;
            }
            try {
                if (! class_exists($class)) {
                    continue;
                }
                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }
                /** @var class-string<Model> $class */
                $models[] = $class;
            } catch (Throwable) {
                continue;
            }
        }
        sort($models);

        return $models;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function safeTable(string $class): ?string
    {
        try {
            /** @var Model $instance */
            $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();

            return $instance->getTable();
        } catch (Throwable) {
            return null;
        }
    }

    private function writeJson(string $path, mixed $data): void
    {
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
