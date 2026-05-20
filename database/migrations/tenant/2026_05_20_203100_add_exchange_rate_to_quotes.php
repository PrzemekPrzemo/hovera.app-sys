<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot kursu wymiany walut na quote'cie. Patrz
 * docs/MARKETPLACE-ROADMAP.md "Multi-currency z NBP exchange rate".
 *
 * `exchange_rate_to_pln` — kurs średni NBP użyty do konwersji w momencie
 * wystawienia oferty. Snapshot żeby zmiana kursu w przyszłości nie
 * modyfikowała historycznych ofert. NULL gdy quote w PLN (brak konwersji).
 *
 * `exchange_rate_date` — data NBP-owej tabeli A użytej do konwersji.
 * Pomocna dla audytu / referencji KSeF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('exchange_rate_to_pln', 10, 4)->nullable()->after('currency');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate_to_pln');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate_to_pln', 'exchange_rate_date']);
        });
    }
};
