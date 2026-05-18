<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozszerzenie KSeF na `transport_invoices`. Patrz docs/TRANSPORT.md
 * §14.1 (KSeF integracja per-transporter).
 *
 * Pierwotna migracja (2026_05_18_124925) zostawiła placeholdery
 * (`ksef_status`, `ksef_reference`, `ksef_sent_at`) z myślą o tym PR.
 * Tutaj dokładamy resztę:
 *
 *   - `ksef_reference_number` — alias / rozszerzenie istniejącego
 *     `ksef_reference` (MF zwraca długi identyfikator po `submit`); zostaje
 *     dla kompatybilności z istniejącym CentralKsefService skeletonem.
 *   - `ksef_submitted_at` / `ksef_accepted_at` — pełen ślad cyklu
 *     submitted → accepted/rejected (asynchroniczne callback'i KSeF).
 *   - `ksef_xml` — wygenerowany payload FA(2/3), cache po stronie
 *     transportera (debug + ponowne wysyłki).
 *   - `ksef_error_payload` — pełna odpowiedź MF przy rejected/error
 *     (kod, message, raw body — bez tokenu).
 *
 * Index `(ksef_status, ksef_submitted_at)` zoptymalizowany pod przyszły
 * cron job pollujący KSeF dla wierszy `submitted` starszych niż X minut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_invoices', function (Blueprint $table) {
            // Nowy kanoniczny identyfikator zwracany przez KSeF po
            // poprawnym submit. Stary `ksef_reference` zostaje dla
            // kompatybilności (skeleton CentralKsefService nadal go używa).
            $table->string('ksef_reference_number', 191)->nullable()->after('ksef_reference');

            $table->timestamp('ksef_submitted_at')->nullable()->after('ksef_sent_at');
            $table->timestamp('ksef_accepted_at')->nullable()->after('ksef_submitted_at');

            $table->longText('ksef_xml')->nullable()->after('ksef_accepted_at');
            $table->json('ksef_error_payload')->nullable()->after('ksef_xml');

            // Cron job „pending KSeF verification" będzie filtrował
            // submitted starsze niż N minut → osobny indeks.
            $table->index(['ksef_status', 'ksef_submitted_at'], 'transport_invoices_ksef_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transport_invoices', function (Blueprint $table) {
            $table->dropIndex('transport_invoices_ksef_pending_idx');
            $table->dropColumn([
                'ksef_reference_number',
                'ksef_submitted_at',
                'ksef_accepted_at',
                'ksef_xml',
                'ksef_error_payload',
            ]);
        });
    }
};
