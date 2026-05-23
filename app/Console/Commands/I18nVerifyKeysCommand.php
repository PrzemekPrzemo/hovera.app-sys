<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Weryfikuje kompletność tłumaczeń. Skanuje całe `app/` i `resources/views/`
 * w poszukiwaniu wywołań `__('key.path')` / `trans('key.path')` /
 * `@lang('key.path')` i sprawdza, czy każdy klucz istnieje w PL + EN.
 *
 * Cel: wykryć brakujące tłumaczenia ZANIM user zobaczy `app/some.key`
 * w UI. Wpinane w CI po `php artisan test` — exit 1 gdy brakuje.
 *
 * Użycie:
 *   php artisan i18n:verify-keys              — sprawdza PL + EN
 *   php artisan i18n:verify-keys --locale=de  — tylko jeden locale
 *   php artisan i18n:verify-keys --orphans    — listuje też klucze
 *                                                zdefiniowane ale nigdy
 *                                                niewywołane przez __()
 *
 * Exit codes:
 *   0 — wszystkie używane klucze mają tłumaczenia w sprawdzanych locale
 *   1 — 1+ brakujący klucz
 */
class I18nVerifyKeysCommand extends Command
{
    protected $signature = 'i18n:verify-keys
        {--locale=pl,en : Comma-separated locales to check (default: pl,en)}
        {--orphans : Also report keys defined in lang files but never referenced in code}';

    protected $description = 'Verify every __()/trans()/@lang() key has translations in PL/EN (catches missing translations).';

    public function handle(): int
    {
        $locales = array_map('trim', explode(',', (string) $this->option('locale')));

        $usedKeys = $this->scanUsedKeys();
        $this->info('Found '.count($usedKeys).' distinct translation keys used in code.');

        $missingByLocale = [];
        foreach ($locales as $locale) {
            $available = $this->loadLocaleKeys($locale);
            $missing = [];
            foreach ($usedKeys as $key => $occurrences) {
                if (! $this->keyExists($key, $available)) {
                    $missing[$key] = $occurrences[0]; // first occurrence file:line
                }
            }
            $missingByLocale[$locale] = $missing;

            if ($missing === []) {
                $this->info("  ✓ {$locale}: all keys translated");
            } else {
                $this->line("  ✗ {$locale}: ".count($missing).' missing keys');
            }
        }

        if ($this->option('orphans')) {
            $this->reportOrphans($usedKeys, $locales);
        }

        $totalMissing = array_sum(array_map('count', $missingByLocale));
        if ($totalMissing === 0) {
            $this->newLine();
            $this->info('✓ All translation keys verified across '.implode(', ', $locales).'.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("✗ Total missing translations: {$totalMissing}");
        foreach ($missingByLocale as $locale => $missing) {
            if ($missing === []) {
                continue;
            }
            $this->newLine();
            $this->line("<fg=red>Missing in {$locale}:</>");
            $rows = [];
            foreach (array_slice($missing, 0, 30, true) as $key => $firstOccurrence) {
                $rows[] = [$key, $firstOccurrence];
            }
            $this->table(['Key', 'First occurrence'], $rows);
            if (count($missing) > 30) {
                $this->line('  ... and '.(count($missing) - 30).' more');
            }
        }

        return self::FAILURE;
    }

    /**
     * Skanuje app/ + resources/views/ w poszukiwaniu __('...') / trans('...') / @lang('...').
     *
     * @return array<string, list<string>> key → [file:line, ...]
     */
    private function scanUsedKeys(): array
    {
        $keys = [];
        $patterns = [
            '/\b__\(\s*[\'"]([\w\/.\-]+)[\'"]/',
            '/\btrans\(\s*[\'"]([\w\/.\-]+)[\'"]/',
            '/@lang\(\s*[\'"]([\w\/.\-]+)[\'"]/',
        ];

        foreach (Finder::create()->files()->in([app_path(), resource_path('views')])->name(['*.php', '*.blade.php']) as $file) {
            $content = file_get_contents($file->getRealPath()) ?: '';
            $lines = explode("\n", $content);
            foreach ($lines as $lineNo => $line) {
                // Pomijamy linie komentarzy (`// ...`, `* ...` z docblock, `{{-- --}}`),
                // bo regex łapie tam przykłady jak `__('key.path')` z PHPDoc.
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '{{--')) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $line, $matches)) {
                        foreach ($matches[1] as $key) {
                            // Heurystyka: klucze i18n maja kropke (np. 'common.actions.save')
                            // lub slash + kropke (np. 'admin/health_checks.title').
                            // Stale jak '__construct' albo 'now' bez kropki = skip.
                            if (! str_contains($key, '.')) {
                                continue;
                            }
                            $occurrence = str_replace(base_path().'/', '', $file->getRealPath()).':'.($lineNo + 1);
                            $keys[$key][] = $occurrence;
                        }
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * Ładuje wszystkie klucze z `lang/{locale}/**\/*.php` jako flat dot-notation.
     *
     * @return array<string, true>
     */
    private function loadLocaleKeys(string $locale): array
    {
        $localePath = lang_path($locale);
        if (! is_dir($localePath)) {
            $this->warn("Locale directory not found: {$localePath}");

            return [];
        }

        $keys = [];
        foreach (Finder::create()->files()->in($localePath)->name('*.php') as $file) {
            $relative = str_replace([$localePath.'/', '.php'], ['', ''], $file->getRealPath());
            // Plik admin/health_checks.php → namespace klucza: 'admin/health_checks'
            $namespace = $relative;
            try {
                $data = require $file->getRealPath();
            } catch (\Throwable) {
                continue;
            }
            if (! is_array($data)) {
                continue;
            }
            $this->flatten($data, $namespace, $keys);
        }

        return $keys;
    }

    /**
     * @param  array<string,true>  $out
     */
    private function flatten(array $data, string $prefix, array &$out): void
    {
        foreach ($data as $k => $v) {
            $fullKey = $prefix.'.'.$k;
            if (is_array($v)) {
                $this->flatten($v, $fullKey, $out);
            } else {
                $out[$fullKey] = true;
            }
        }
    }

    /**
     * @param  array<string,true>  $available
     */
    private function keyExists(string $key, array $available): bool
    {
        // Exact match — najczestszy.
        if (isset($available[$key])) {
            return true;
        }

        // Wildcardy / dynamic: jesli kod uzywa __('foo.bar.'.$something) albo
        // __('foo.bar_'.$type), grep zlapie tylko prefiks. Zezwalamy gdy
        // istnieje jakikolwiek klucz z tym prefiksem — heurystyka (pewnie
        // false positives, ale lepiej zaniedbywalnie niz blokujac CI na
        // dynamic keys ktore istnieja).
        if (str_ends_with($key, '.') || str_ends_with($key, '_')) {
            foreach (array_keys($available) as $available_key) {
                if (str_starts_with($available_key, $key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $usedKeys
     * @param  list<string>  $locales
     */
    private function reportOrphans(array $usedKeys, array $locales): void
    {
        $this->newLine();
        $this->info('Scanning for orphan keys (defined in lang files but never used in code)...');

        foreach ($locales as $locale) {
            $available = $this->loadLocaleKeys($locale);
            $orphans = [];
            foreach (array_keys($available) as $key) {
                if (! isset($usedKeys[$key])) {
                    $orphans[] = $key;
                }
            }
            if ($orphans === []) {
                $this->line("  ✓ {$locale}: no orphan keys");

                continue;
            }
            $this->line("  · {$locale}: ".count($orphans).' orphan keys');
            foreach (array_slice($orphans, 0, 10) as $orphan) {
                $this->line("    - {$orphan}");
            }
            if (count($orphans) > 10) {
                $this->line('    ... and '.(count($orphans) - 10).' more');
            }
        }
    }
}
