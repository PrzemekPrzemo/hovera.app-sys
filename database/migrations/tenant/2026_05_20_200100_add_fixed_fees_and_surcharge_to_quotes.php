<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot na quote: fixed fees (JSON) + surcharge percent + wyliczona
 * kwota surcharge. Patrz docs/MARKETPLACE-ROADMAP.md "Calculator parity".
 *
 * Snapshot per quote chroni historyczne wyceny przed efektem zmiany
 * defaultów w TransportSettings — analogicznie jak rate_per_km,
 * fuel_*, extra_horse_fee_snapshot.
 *
 * Default '[]' / 0 zachowuje backward compat — kalkulator legacy bez
 * ustawień nic nie dolicza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->json('fixed_fees_snapshot')->nullable()->after('extra_horse_fee_snapshot');
            $table->decimal('surcharge_percent_snapshot', 5, 2)->nullable()->after('fixed_fees_snapshot');
            $table->decimal('surcharge_amount_snapshot', 10, 2)->nullable()->after('surcharge_percent_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'fixed_fees_snapshot',
                'surcharge_percent_snapshot',
                'surcharge_amount_snapshot',
            ]);
        });
    }
};
