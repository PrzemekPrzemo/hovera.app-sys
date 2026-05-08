<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\InvoiceKind;
use App\Models\Central\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-stable invoice numbering.
 *
 * Każda stajnia konfiguruje template (np. `FV/{seq}/{MM}/{YYYY}`) +
 * interval resetu (rocznie / miesięcznie / nigdy). Domyślnie:
 *   FV     → "FV/{seq}/{MM}/{YYYY}", reset rocznie
 *   PRO    → "PRO/{seq}/{MM}/{YYYY}", reset rocznie
 *   KOR    → "KOR/{seq}/{MM}/{YYYY}", reset rocznie
 *
 * Dostępne placeholdery:
 *   {seq}        — kolejny numer w danym scope (1, 2, 3, ...)
 *   {seq:NN}     — wypełnione zerami do NN cyfr (np. {seq:4} → 0001)
 *   {YYYY}       — rok 4-cyfrowy
 *   {YY}         — rok 2-cyfrowy
 *   {MM}         — miesiąc 2-cyfrowy
 *   {M}          — miesiąc 1- lub 2-cyfrowy
 *   {DD}         — dzień 2-cyfrowy
 *   {prefix}     — settings.invoicing.prefix (np. "STW")
 *
 * Atomicity: counter increment w transakcji DB (FOR UPDATE).
 */
class InvoiceNumberGenerator
{
    public const DEFAULT_TEMPLATES = [
        InvoiceKind::Fv->value => 'FV/{seq}/{MM}/{YYYY}',
        InvoiceKind::FvProforma->value => 'PRO/{seq}/{MM}/{YYYY}',
        InvoiceKind::FvKorekta->value => 'KOR/{seq}/{MM}/{YYYY}',
    ];

    /**
     * @return array<string,string>
     */
    public static function resetOptions(): array
    {
        return [
            'yearly' => __('app/invoicing_settings.reset_options.yearly'),
            'monthly' => __('app/invoicing_settings.reset_options.monthly'),
            'never' => __('app/invoicing_settings.reset_options.never'),
        ];
    }

    /**
     * Wygeneruj kolejny numer dla danego rodzaju faktury i daty wystawienia.
     */
    public function next(Tenant $tenant, InvoiceKind $kind, ?Carbon $issueDate = null): string
    {
        $issueDate ??= Carbon::now();

        $template = $this->templateFor($tenant, $kind);
        $resetInterval = $this->resetIntervalFor($tenant);
        $scope = $this->scopeKey($kind, $resetInterval, $issueDate);

        $seq = $this->incrementCounter($scope);

        return $this->renderTemplate($template, $seq, $issueDate, $tenant);
    }

    /**
     * Tylko render — bez incrementa. Użyteczne dla podglądu w UI
     * (placeholder przy tworzeniu draftu).
     */
    public function preview(Tenant $tenant, InvoiceKind $kind, int $seq = 1, ?Carbon $issueDate = null): string
    {
        $issueDate ??= Carbon::now();
        $template = $this->templateFor($tenant, $kind);

        return $this->renderTemplate($template, $seq, $issueDate, $tenant);
    }

    private function templateFor(Tenant $tenant, InvoiceKind $kind): string
    {
        $custom = (string) (data_get($tenant->settings, "invoicing.template.{$kind->value}") ?? '');
        if ($custom !== '') {
            return $custom;
        }

        return self::DEFAULT_TEMPLATES[$kind->value] ?? 'INV/{seq}/{MM}/{YYYY}';
    }

    private function resetIntervalFor(Tenant $tenant): string
    {
        $value = (string) (data_get($tenant->settings, 'invoicing.reset_interval') ?? 'yearly');

        return in_array($value, ['yearly', 'monthly', 'never'], true) ? $value : 'yearly';
    }

    private function scopeKey(InvoiceKind $kind, string $resetInterval, Carbon $issueDate): string
    {
        return match ($resetInterval) {
            'monthly' => "{$kind->value}:{$issueDate->format('Y-m')}",
            'yearly' => "{$kind->value}:{$issueDate->format('Y')}",
            default => $kind->value,
        };
    }

    /**
     * Atomic increment of counter. Pozostaje w obrębie current
     * tenant DB connection (TenantManager już ją wskazuje).
     */
    private function incrementCounter(string $scope): int
    {
        return DB::connection('tenant')->transaction(function () use ($scope) {
            // Upsert + lock — proste i deterministyczne.
            $row = DB::connection('tenant')
                ->table('invoice_counters')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::connection('tenant')->table('invoice_counters')->insert([
                    'scope' => $scope,
                    'seq' => 1,
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->seq + 1;
            DB::connection('tenant')->table('invoice_counters')
                ->where('scope', $scope)
                ->update(['seq' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    private function renderTemplate(string $template, int $seq, Carbon $date, Tenant $tenant): string
    {
        $prefix = (string) (data_get($tenant->settings, 'invoicing.prefix') ?? '');

        $replacements = [
            '{seq}' => (string) $seq,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{M}' => (string) (int) $date->format('m'),
            '{DD}' => $date->format('d'),
            '{prefix}' => $prefix,
        ];

        $out = strtr($template, $replacements);

        // {seq:NN} — zero-pad sequence to NN digits (np. {seq:4} → 0001)
        $out = preg_replace_callback('/\{seq:(\d+)\}/', function ($m) use ($seq) {
            $width = (int) $m[1];

            return str_pad((string) $seq, $width, '0', STR_PAD_LEFT);
        }, $out);

        return (string) $out;
    }
}
