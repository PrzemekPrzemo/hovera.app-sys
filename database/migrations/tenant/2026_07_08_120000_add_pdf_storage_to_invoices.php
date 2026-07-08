<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local-hosted PDF persistence dla tenant `invoices`.
 *
 * Business decision (owner, 2026-07): hovera.app hostuje PDF faktury na
 * własnym storage'u (dysk lokalny, konfigurowalny przez `INVOICE_PDF_DISK`)
 * przez rok wystawienia + 1 miesiąc grace (np. FV z 2026 → hostowana do
 * końca stycznia 2027). Po tym terminie klient jest kierowany do KSeF
 * (trwały zapis każdej wysłanej faktury) zamiast do lokalnego pliku.
 *
 * Patrz `App\Services\Invoicing\InvoicePdfStorageService` + `docs/API.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('pdf_disk', 32)->nullable()->after('metadata');
            $table->string('pdf_path', 191)->nullable()->after('pdf_disk');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pdf_disk', 'pdf_path', 'pdf_generated_at']);
        });
    }
};
