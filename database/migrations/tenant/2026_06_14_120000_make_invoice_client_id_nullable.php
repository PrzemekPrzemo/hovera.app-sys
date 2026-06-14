<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pozwala wystawiać FV dla odbiorcy ad-hoc — nie wymagamy istniejącego
 * Client'a w bazie. Snapshot fields (`buyer_name`, `buyer_nip`,
 * `buyer_address`, `buyer_postal_code`, `buyer_city`, `buyer_country`,
 * `buyer_type`) już są — wystarczy, że client_id stanie się nullable.
 *
 * Historyczne FV: wszystkie powiązane z klientami z bazy zachowują FK.
 * Nowe ad-hoc FV: client_id = NULL, dane nabywcy z formularza.
 *
 * Filament form (`InvoiceResource`) dostaje toggle "Klient z bazy" /
 * "Jednorazowy odbiorca" — przełącza widoczność client_id Select vs
 * pełnego bloku buyer_* manualnie.
 *
 * MySQL nie pozwala bezpośrednio na `change()` z foreign key — drop +
 * re-add z nullable=true. Na SQLite (tests) leci tym samym kodem dzięki
 * abstrakcji Blueprint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Drop istniejącego FK + indexu (Laravel auto-generuje nazwy
            // wg konwencji `{table}_{col}_foreign` / `_index`).
            $table->dropForeign(['client_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('client_id', 26)->nullable()->change();
            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Rollback wymaga, by wszystkie istniejące FV miały client_id —
        // jeśli ktoś wystawił FV bez klienta w międzyczasie, rollback
        // zostawi je w niespójnym stanie (NULL pomimo NOT NULL). To
        // pragmatic — nie planujemy rollback'a w prod.
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('client_id', 26)->nullable(false)->change();
            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->restrictOnDelete();
        });
    }
};
