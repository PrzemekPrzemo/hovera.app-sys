<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Ksef\JpkFa3Exporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Eksportuje JPK_FA(3) — agregat wszystkich wystawionych faktur stajni
 * za okres (rok lub kwartał). XML wrzucany na `local` disk lub stdout
 * gdy `--print`.
 *
 * Use case: księgowy stajni potrzebuje JPK na żądanie US — operator
 * wykonuje komendę i przesyła plik do urzędu (przez e-Deklaracje albo
 * portal podatnika).
 *
 * Przykłady:
 *   php artisan ksef:export-jpk-fa3 my-stable 2026 2     # Q2 2026
 *   php artisan ksef:export-jpk-fa3 my-stable 2026       # cały rok
 *   php artisan ksef:export-jpk-fa3 my-stable 2026 2 --print  # do stdout
 *   php artisan ksef:export-jpk-fa3 my-stable 2026 2 --disk=jpk-archive
 */
class KsefExportJpkFa3Command extends Command
{
    protected $signature = 'ksef:export-jpk-fa3 {tenant} {year} {quarter?} {--print} {--disk=local} {--path=}';

    protected $description = 'Generate JPK_FA(3) XML aggregate of issued invoices for a tenant and period.';

    public function handle(JpkFa3Exporter $exporter): int
    {
        $slug = (string) $this->argument('tenant');
        $year = (int) $this->argument('year');
        $quarter = $this->argument('quarter') !== null ? (int) $this->argument('quarter') : null;

        if ($year < 2020 || $year > 2099) {
            $this->error('Year out of range: '.$year.' (expected 2020-2099).');

            return self::FAILURE;
        }

        if ($quarter !== null && ($quarter < 1 || $quarter > 4)) {
            $this->error('Quarter must be 1-4, got: '.$quarter);

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->error('Tenant not found: '.$slug);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Exporting JPK_FA(3) for %s — %s',
            $tenant->name,
            $quarter !== null ? 'Q'.$quarter.' '.$year : 'full year '.$year,
        ));

        $xml = $quarter !== null
            ? $exporter->exportQuarter($tenant, $year, $quarter)
            : $exporter->exportYear($tenant, $year);

        if ($this->option('print')) {
            $this->line($xml);

            return self::SUCCESS;
        }

        $path = (string) ($this->option('path') ?: $this->defaultPath($slug, $year, $quarter));
        $disk = (string) $this->option('disk');

        Storage::disk($disk)->put($path, $xml);

        $this->info(sprintf(
            'Saved %d bytes to %s:%s',
            strlen($xml),
            $disk,
            $path,
        ));

        return self::SUCCESS;
    }

    private function defaultPath(string $slug, int $year, ?int $quarter): string
    {
        $base = 'jpk/'.$slug.'/';

        return $quarter !== null
            ? $base.$year.'-Q'.$quarter.'.xml'
            : $base.$year.'.xml';
    }
}
