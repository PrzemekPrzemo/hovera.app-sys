<?php

declare(strict_types=1);

namespace App\Domain\Transport\Quotes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant numeracja ofert transportowych. Wzorzec analogiczny do
 * InvoiceNumberGenerator — atomic increment w transakcji `FOR UPDATE`
 * + zero-padding sequence + monthly/yearly reset.
 *
 * Domyślny format: OF/{YYYY}/{MM}/{seq:4}  → "OF/2026/05/0001"
 * Placeholdery:
 *   {seq}       — kolejny numer
 *   {seq:NN}    — zero-pad do NN cyfr (np. {seq:4} → 0001)
 *   {YYYY} {YY} — rok 4-/2-cyfrowy
 *   {MM}        — miesiąc 2-cyfrowy
 *   {M}         — miesiąc 1- lub 2-cyfrowy
 *   {DD}        — dzień 2-cyfrowy
 *
 * Patrz docs/TRANSPORT.md §9 faza 3.
 */
class QuoteNumberGenerator
{
    public const DEFAULT_TEMPLATE = 'OF/{YYYY}/{MM}/{seq:4}';

    public const RESET_MONTHLY = 'monthly';

    public const RESET_YEARLY = 'yearly';

    public const RESET_NEVER = 'never';

    public function next(?Carbon $issueDate = null, string $template = self::DEFAULT_TEMPLATE, string $resetInterval = self::RESET_MONTHLY): string
    {
        $issueDate ??= Carbon::now();
        $scope = $this->scopeKey($resetInterval, $issueDate);
        $seq = $this->incrementCounter($scope);

        return $this->renderTemplate($template, $seq, $issueDate);
    }

    /**
     * Tylko render — bez incrementu. Do podglądu w UI (np. „następny numer to OF/2026/05/0004").
     */
    public function preview(int $seq = 1, ?Carbon $issueDate = null, string $template = self::DEFAULT_TEMPLATE): string
    {
        $issueDate ??= Carbon::now();

        return $this->renderTemplate($template, $seq, $issueDate);
    }

    private function scopeKey(string $resetInterval, Carbon $issueDate): string
    {
        return match ($resetInterval) {
            self::RESET_MONTHLY => 'monthly:'.$issueDate->format('Y-m'),
            self::RESET_YEARLY => 'yearly:'.$issueDate->format('Y'),
            default => 'global',
        };
    }

    private function incrementCounter(string $scope): int
    {
        return DB::connection('tenant')->transaction(function () use ($scope) {
            $row = DB::connection('tenant')
                ->table('quote_counters')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::connection('tenant')->table('quote_counters')->insert([
                    'scope' => $scope,
                    'seq' => 1,
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->seq + 1;
            DB::connection('tenant')->table('quote_counters')
                ->where('scope', $scope)
                ->update(['seq' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    private function renderTemplate(string $template, int $seq, Carbon $date): string
    {
        $replacements = [
            '{seq}' => (string) $seq,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{M}' => (string) (int) $date->format('m'),
            '{DD}' => $date->format('d'),
        ];

        $out = strtr($template, $replacements);

        $out = preg_replace_callback('/\{seq:(\d+)\}/', function ($m) use ($seq) {
            $width = (int) $m[1];

            return str_pad((string) $seq, $width, '0', STR_PAD_LEFT);
        }, $out);

        return (string) $out;
    }
}
