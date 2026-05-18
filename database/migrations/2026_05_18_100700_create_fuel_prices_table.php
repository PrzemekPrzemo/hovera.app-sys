<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshoty cen paliw — globalne dla wszystkich tenantów (centralne).
 * Patrz docs/TRANSPORT.md §3.2 (tabela fuel_prices) + §4.4.
 *
 * Scraper e-petrol.pl (lub manual override przez master admin) zapisuje
 * jeden wiersz dziennie per fuel_type. FuelPriceService bierze najnowszy
 * z TTL 7 dni, z fallbackiem do config('transport.fuel.fallback_price').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->string('fuel_type', 16)->index();         // diesel | petrol_95 | petrol_98 | lpg
            $table->decimal('price_pln', 5, 2);
            $table->date('snapshot_date');
            $table->string('source', 32);                      // 'epetrol' | 'manual'
            $table->json('raw_payload')->nullable();           // surowy fragment HTML/JSON dla debug
            $table->timestamp('created_at');

            $table->unique(['fuel_type', 'snapshot_date', 'source'], 'fuel_prices_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('fuel_prices');
    }
};
