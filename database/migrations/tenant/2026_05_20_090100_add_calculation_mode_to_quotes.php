<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `calculation_mode` na ofercie — `one_way` / `round_trip` / `return_home`.
 *
 * Default 'one_way' (poprzednio: brak pola = round_trip toggle). Stare
 * `round_trip` boolean zostaje przez backward-compat — `round_trip=true`
 * traktujemy w model accessor jako synonim mode='round_trip'.
 *
 * Patrz `App\Enums\CalculationMode`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('calculation_mode', 16)
                ->default('one_way')
                ->after('round_trip')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['calculation_mode']);
            $table->dropColumn('calculation_mode');
        });
    }
};
