<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozszerzenie KSeF na regular tenant `invoices` table — mirroruje
 * extension dla `transport_invoices` (2026_05_18_194200). Patrz
 * docs/IMPLEMENTATION-PLAN-PHASE-1.md sekcja PR I3a.
 *
 * Tenant Invoice (boarding/lekcje/pasze stajni) trzymał dotąd tylko
 * placeholdery KSeF (`ksef_status`, `ksef_reference`, `ksef_sent_at`)
 * z myślą o uzupełnieniu gdy domkniemy submit/poll flow dla regular
 * invoices. Ten PR dokłada brakujące kolumny — same model + schema,
 * bez logiki send/poll (ta przyjdzie w follow-up'ie z implementacją
 * `TenantKsefSubmissionService`).
 *
 * Dodatkowo: `ksef_environment` żeby per-faktura zapisać do jakiego
 * środowiska MF poszła (test/demo/prod). Stajnia może w settings
 * przełączyć środowisko — faktury starsze chcemy mieć ślad gdzie
 * faktycznie wylądowały.
 *
 *   - `ksef_reference_number` — kanoniczny ident MF (długi UUID-like
 *     identifier z `elementReferenceNumber`); stary `ksef_reference`
 *     zostaje dla kompatybilności z aktualnym kodem w InvoiceResource.
 *   - `ksef_submitted_at` / `ksef_accepted_at` — pełen cykl
 *     submitted → accepted/rejected (asynchroniczne MF callback'i).
 *   - `ksef_xml` — wygenerowany FA(3) payload (cache do debug + retry).
 *   - `ksef_error_payload` — pełna odpowiedź MF przy rejected/error
 *     (kod, message, raw body — bez tokenu).
 *   - `ksef_environment` — 'test' | 'demo' | 'prod' (per faktura).
 *
 * Index `(ksef_status, ksef_submitted_at)` dla przyszłego cron job'a
 * pollującego pending invoices starsze niż X minut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('ksef_reference_number', 191)->nullable()->after('ksef_reference');

            $table->timestamp('ksef_submitted_at')->nullable()->after('ksef_sent_at');
            $table->timestamp('ksef_accepted_at')->nullable()->after('ksef_submitted_at');

            $table->longText('ksef_xml')->nullable()->after('ksef_accepted_at');
            $table->json('ksef_error_payload')->nullable()->after('ksef_xml');

            $table->string('ksef_environment', 8)->nullable()->after('ksef_error_payload');

            $table->index(['ksef_status', 'ksef_submitted_at'], 'invoices_ksef_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_ksef_pending_idx');
            $table->dropColumn([
                'ksef_reference_number',
                'ksef_submitted_at',
                'ksef_accepted_at',
                'ksef_xml',
                'ksef_error_payload',
                'ksef_environment',
            ]);
        });
    }
};
