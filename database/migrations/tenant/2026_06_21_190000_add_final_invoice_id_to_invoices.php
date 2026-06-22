<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje `final_invoice_id` na invoices — link z faktury zaliczkowej
 * (ZAL, `InvoiceKind::FvZaliczkowa`) do faktury końcowej rozliczającej
 * wszystkie zaliczki. Multi 1:N: jedna final FV może mieć N zaliczek.
 *
 * Lifecycle:
 *   1. Klient płaci zaliczkę → stable wystawia ZAL #1 (final_invoice_id=NULL)
 *   2. Klient płaci kolejną → stable wystawia ZAL #2 (final_invoice_id=NULL)
 *   3. Usługa zrealizowana → stable wystawia final FV
 *   4. Stable klika "Połącz z FV" na każdej ZAL → final_invoice_id=fv.id
 *   5. KsefInvoiceXmlBuilder dla final FV emituje <DaneFaZaliczkowej>
 *      dla każdej zaliczki (referencja: nr + data + kwota brutto)
 *
 * Index `(final_invoice_id)` żeby final FV mógł szybko podciągnąć
 * wszystkie zaliczki (`Invoice::advances()` relation).
 *
 * Patrz docs/IMPLEMENTATION-PLAN-PHASE-1.md sekcja PR I3 — decyzja
 * user'a "Multi 1:N — wiele zaliczek na 1 FV".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('final_invoice_id', 26)
                ->nullable()
                ->after('corrects_invoice_id');

            $table->index('final_invoice_id', 'invoices_final_invoice_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_final_invoice_id_idx');
            $table->dropColumn('final_invoice_id');
        });
    }
};
