<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Konsoliduje wcześniejsze warianty płci konia (filly/colt/foal —
 * młode formy klaczy/ogiera/źrebięcia) do 4 dorosłych wartości
 * jakich oczekuje obecny formularz: mare/gelding/stallion/breeding_stallion.
 *
 * filly  → mare      (klaczka → klacz)
 * colt   → stallion  (ogierek → ogier)
 * foal   → null      (źrebię — brak dorosłego odpowiednika; właściciel
 *                     może później ustawić ręcznie po pierwszej zmianie)
 *
 * Operuje na connection 'tenant' — uruchamia się w bazie każdej stajni
 * przez `php artisan migrate --database=tenant`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tenant')->table('horses')
            ->where('sex', 'filly')
            ->update(['sex' => 'mare']);

        DB::connection('tenant')->table('horses')
            ->where('sex', 'colt')
            ->update(['sex' => 'stallion']);

        DB::connection('tenant')->table('horses')
            ->where('sex', 'foal')
            ->update(['sex' => null]);
    }

    public function down(): void
    {
        // Brak odwracania — utrata informacji którą filly była młoda
        // a która dorosła. Migracja jest one-way.
    }
};
