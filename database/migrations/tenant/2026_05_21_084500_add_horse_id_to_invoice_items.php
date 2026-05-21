<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faza 3 PR 3.1 Owner ↔ Stable shared view — patrz
 * docs/OWNER-STABLE-ROADMAP.md.
 *
 * Dodaje nullable `horse_id` (ULID, soft FK) na `invoice_items` żeby
 * faktury wystawione za boarding mogły być filtrowane per koń. Owner
 * panel wykorzysta to do widoku "Faktury za Iskrę w 2026", auto-billing
 * job (PR 3.2) będzie wypełniał to pole snapshotem central_horse_id.
 *
 * Backward-compat:
 *   - Istniejące invoice_items mają horse_id=NULL (manual invoices ze
 *     stable side bez per-horse linka)
 *   - Nowe items z auto-billing zawsze wypełnione
 *
 * Soft FK: nie dodajemy `constrained()` bo central_horse_id wskazuje na
 * tabelę z OBCEJ DB (central DB, nie tenant DB). Stable tenant nie ma
 * fizycznej tabeli central_horse_registry w swojej DB — referencja jest
 * logiczna, walidowana w aplikacji.
 *
 * Idempotent: hasColumn check przed addColumn — gdy migration zostanie
 * uruchomiona ponownie (np. partial deploy fail) nie crashuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'horse_id')) {
                $table->string('horse_id', 26)
                    ->nullable()
                    ->after('invoice_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'horse_id')) {
                $table->dropIndex(['horse_id']);
                $table->dropColumn('horse_id');
            }
        });
    }
};
