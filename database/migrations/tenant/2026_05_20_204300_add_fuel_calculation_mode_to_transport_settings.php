<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tryb kalkulacji paliwa — patrz docs/MARKETPLACE-ROADMAP.md
 * "Calculator: fuel mode toggle (surcharge vs full cost)".
 *
 * Tryby:
 *   surcharge — domyślny, dolicza tylko RÓŻNICĘ między aktualną ceną
 *               a `fuel_base_price_pln` × spalanie × dystans.
 *               Sens: rate_per_km zakłada bazową cenę paliwa, surcharge
 *               kompensuje zmianę względem bazy.
 *   full_cost — dolicza PEŁEN koszt paliwa (cena × spalanie × dystans),
 *               niezależnie od bazy. Sens: rate_per_km TYLKO koszt
 *               pracy/serwisu/marży, paliwo płaci klient 1:1.
 *
 * Default 'surcharge' zachowuje stary behaviour. Migracja `fuel_surcharge_enabled`
 * (boolean) zostaje dla backward compat — gdy ten flag = false, mode jest
 * efektywnie ignorowany (paliwo = 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->string('fuel_calculation_mode', 16)
                ->default('surcharge')
                ->after('fuel_surcharge_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn('fuel_calculation_mode');
        });
    }
};
