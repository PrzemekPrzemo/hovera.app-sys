<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot kursu NBP na fakturze. Art. 31a ust. 1 ustawy o VAT: kwoty
 * w walucie obcej przeliczamy po średnim kursie NBP z ostatniego dnia
 * ROBOCZEGO poprzedzającego dzień wystawienia FV. Snapshot trzymamy
 * immutable na rekordzie FV — re-issue (korekta) wystawia nową FV
 * z nowym snapshot'em.
 *
 *   exchange_rate         — kurs PLN per 1 jednostka waluty (decimal 10,4)
 *   exchange_rate_date    — effectiveDate z tabeli NBP (data publikacji
 *                            kursu, niekoniecznie issued_at - 1 — może być
 *                            wcześniej jeśli był weekend/święto)
 *   exchange_rate_source  — 'nbp_a' (tabela A), 'pln_base' (PLN, no-op),
 *                            'manual' (override przez usera w przyszłości)
 *
 * Wszystkie nullable bo PLN FV nie potrzebują snapshot'u.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'transport_invoices'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('exchange_rate', 14, 6)->nullable()->after('currency');
                $t->date('exchange_rate_date')->nullable()->after('exchange_rate');
                $t->string('exchange_rate_source', 16)->nullable()->after('exchange_rate_date');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'transport_invoices'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['exchange_rate', 'exchange_rate_date', 'exchange_rate_source']);
            });
        }
    }
};
