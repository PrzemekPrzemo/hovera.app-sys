<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodatkowe pozycje wyceny (ad-hoc line items) — patrz
 * docs/MARKETPLACE-ROADMAP.md "Calculator: quote_items line items + PDF".
 *
 * Lista [{name, quantity, unit?, unit_price_net, line_total_net}] przeznaczona
 * do dopisania ekstrów których kalkulator nie ma:
 *   - Postój dodatkowy 4h × 50 PLN = 200 PLN
 *   - Specjalne wyposażenie
 *   - Opłata dokumentacyjna
 *   - itp.
 *
 * Pozycje są ADDITIVE do net_total — kwota items'ów dolicza się do
 * net z kalkulatora, VAT i gross są przeliczane.
 *
 * Default '[]' = brak items'ów (backward compat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->json('line_items')->nullable()->after('surcharge_amount_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('line_items');
        });
    }
};
