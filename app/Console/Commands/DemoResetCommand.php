<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Nightly reset of the public demo tenant — wipes user-edited data and
 * re-seeds a clean baseline. Scheduled at 22:00 (see routes/console.php).
 *
 * Implementation is just `hovera:demo:seed --fresh` against the demo slug.
 * Existing owner / tenant row stays put so /demo never loses its target.
 */
class DemoResetCommand extends Command
{
    protected $signature = 'hovera:demo:reset
        {--slug= : Override demo slug (defaults to config hovera.demo.slug)}';

    protected $description = 'Czyści demo tenant i przywraca świeży zestaw danych. Schedulowane na 22:00.';

    public function handle(): int
    {
        $slug = (string) ($this->option('slug') ?: config('hovera.demo.slug', 'demo'));

        // withTrashed bo demo tenant może być soft-deleted (np. master admin
        // skasował przez Filament). DemoSeedCommand też restore'uje (PR #140).
        $tenant = Tenant::query()->withTrashed()->where('slug', $slug)->first();
        if (! $tenant) {
            $this->warn("Demo tenant '{$slug}' nie istnieje — uruchom najpierw `hovera:demo:seed --slug={$slug}`.");

            return self::FAILURE;
        }
        if ($tenant->trashed()) {
            $this->warn("Demo tenant '{$slug}' był soft-deleted — przywracam.");
            $tenant->restore();
        }

        $this->info("Resetuję demo tenant '{$slug}'…");

        $exit = Artisan::call('hovera:demo:seed', [
            '--slug' => $slug,
            '--fresh' => true,
        ], $this->getOutput());

        if ($exit !== 0) {
            $this->error('Reset nie powiódł się — sprawdź log.');

            return self::FAILURE;
        }

        $this->info("✓ Demo '{$slug}' zresetowane.");

        return self::SUCCESS;
    }
}
