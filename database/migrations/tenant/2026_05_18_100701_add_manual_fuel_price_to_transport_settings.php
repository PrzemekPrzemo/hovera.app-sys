<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant manual override aktualnej ceny ON — gdy transporter chce
 * zafiksować cenę paliwa do swoich faktur (np. ma długoterminowy kontrakt
 * z dostawcą po stałej cenie). NULL = używaj wartości z central fuel_prices
 * (scraper e-petrol).
 *
 * UWAGA: kolumna `fuel_base_price_pln` z poprzedniej migracji to BAZA do
 * naliczania surcharge'a (cena, powyżej której doliczamy narzut), a NOWA
 * `manual_fuel_price_pln` to AKTUALNA cena paliwa (do liczenia surcharge'a
 * dla istniejących zleceń). To dwa różne pojęcia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('transport_settings', function (Blueprint $table) {
            $table->decimal('manual_fuel_price_pln', 5, 2)->nullable()->after('fuel_base_price_pln');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('transport_settings', function (Blueprint $table) {
            $table->dropColumn('manual_fuel_price_pln');
        });
    }
};
