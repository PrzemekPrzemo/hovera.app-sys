<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domyślna stawka za każdego dodatkowego konia powyżej pierwszego.
 *
 * Logika cenówki (patrz CalculatorService::calculate()):
 *   extra_horses = max(0, horses_count - 1) × extra_horse_fee_default
 *   subtotal     = base_cost + fuel_surcharge + extra_horses
 *
 * `0` (default) zachowuje stary behaviour — kalkulator zwraca tę samą cenę
 * niezależnie od liczby koni. Transporter ustawia własną wartość w
 * /transport/settings (np. 150 PLN / dodatkowy koń).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->decimal('extra_horse_fee_default', 8, 2)->default(0)->after('minimum_charge');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn('extra_horse_fee_default');
        });
    }
};
