<?php

declare(strict_types=1);

namespace App\Domain\Transport\Invoices;

use App\Enums\TransportInvoiceKind;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant numeracja faktur transportowych. Wzorzec analogiczny do
 * QuoteNumberGenerator + hovera InvoiceNumberGenerator — atomic increment
 * w transakcji `FOR UPDATE` na `transport_invoice_counters`, monthly/yearly/
 * never reset.
 *
 * Format domyślny per kind:
 *   Fv        → FT/{YYYY}/{MM}/{seq:4}  ("FT/2026/05/0001")
 *   Proforma  → PRO/{YYYY}/{MM}/{seq:4}
 *   Korekta   → KOR/{YYYY}/{MM}/{seq:4}
 *
 * Patrz docs/TRANSPORT.md §9 faza 3.
 */
class TransportInvoiceNumberGenerator
{
    public const RESET_MONTHLY = 'monthly';

    public const RESET_YEARLY = 'yearly';

    public const RESET_NEVER = 'never';

    public function next(
        TransportInvoiceKind $kind = TransportInvoiceKind::Fv,
        ?Carbon $issueDate = null,
        ?string $template = null,
        string $resetInterval = self::RESET_MONTHLY,
    ): string {
        $issueDate ??= Carbon::now();
        $template ??= $kind->defaultTemplate();
        $scope = $this->scopeKey($kind, $resetInterval, $issueDate);
        $seq = $this->incrementCounter($scope);

        return $this->renderTemplate($template, $seq, $issueDate);
    }

    public function preview(
        int $seq = 1,
        TransportInvoiceKind $kind = TransportInvoiceKind::Fv,
        ?Carbon $issueDate = null,
        ?string $template = null,
    ): string {
        $issueDate ??= Carbon::now();
        $template ??= $kind->defaultTemplate();

        return $this->renderTemplate($template, $seq, $issueDate);
    }

    private function scopeKey(TransportInvoiceKind $kind, string $resetInterval, Carbon $issueDate): string
    {
        return match ($resetInterval) {
            self::RESET_MONTHLY => $kind->value.':'.$issueDate->format('Y-m'),
            self::RESET_YEARLY => $kind->value.':'.$issueDate->format('Y'),
            default => $kind->value,
        };
    }

    private function incrementCounter(string $scope): int
    {
        return DB::connection('tenant')->transaction(function () use ($scope) {
            $row = DB::connection('tenant')
                ->table('transport_invoice_counters')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::connection('tenant')->table('transport_invoice_counters')->insert([
                    'scope' => $scope,
                    'seq' => 1,
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->seq + 1;
            DB::connection('tenant')->table('transport_invoice_counters')
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
