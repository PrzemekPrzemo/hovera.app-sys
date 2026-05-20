<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calculator parity z TransportKoni-Kalkulator: stałe opłaty (autostrady,
 * prom, etc.) + marża procentowa per wycena.
 *
 * `fixed_fees_default` JSON — lista [{ name, amount }] do prefilla nowej
 * wyceny. Transporter ustawia raz w settings'ach, kalkulator dolicza
 * automatycznie. Quotes snapshot'ują swój własny JSON żeby zmiana
 * settings nie modyfikowała historycznych wycen.
 *
 * `surcharge_percent_default` % — marża doliczana po kosztach bazowych
 * (base + fuel + extra_horse + fixed_fees + minimum_adjustment), przed VAT.
 * Null = brak marży. Wartości typowe: 10–25%.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md "Calculator parity z TransportKoni".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->json('fixed_fees_default')->nullable()->after('extra_horse_fee_default');
            $table->decimal('surcharge_percent_default', 5, 2)->nullable()->after('fixed_fees_default');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn(['fixed_fees_default', 'surcharge_percent_default']);
        });
    }
};
