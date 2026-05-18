<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton z konfiguracją transportową — stawki, paliwo, VAT, waluta,
 * provider routingu. Patrz docs/TRANSPORT.md §4.4.
 *
 * Tabela ma być wypełniona jednym wierszem na tenant; pierwszy odczyt
 * z UI auto-tworzy wiersz z domyślnymi wartościami.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_settings', function (Blueprint $table) {
            $table->id();

            // Stawki
            $table->decimal('rate_per_km', 6, 2)->default(4.50);          // PLN/km bez ładunku
            $table->decimal('rate_per_km_loaded', 6, 2)->nullable();      // PLN/km z koniem (jeśli różna)
            $table->decimal('minimum_charge', 8, 2)->default(800.00);     // min. opłata zlecenia

            // Paliwo
            $table->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $table->boolean('fuel_surcharge_enabled')->default(true);
            $table->decimal('fuel_base_price_pln', 5, 2)->default(7.00);  // baza, powyżej której doliczamy surcharge

            // Podatki + waluta
            $table->decimal('vat_rate', 4, 2)->default(23.00);
            $table->string('currency', 3)->default('PLN');

            // Routing provider — JSON, bo trzymamy parametry per-provider
            // (np. {"provider":"google","api_key":"..."}). Defaults do ORS,
            // bo tak ustawiamy plan Solo (patrz docs/TRANSPORT.md §7.2).
            $table->json('routing_provider')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_settings');
    }
};
