<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waypoints per-wycena — patrz docs/MARKETPLACE-ROADMAP.md
 * "Waypoints + reorder + POI library".
 *
 * Każda quote może mieć 0..N waypoint'ów MIĘDZY pickup_address a
 * dropoff_address. CalculatorService multi-leg routing sumuje segmenty
 * (pickup → wp1 → wp2 → ... → dropoff). Kolejność według `sort_order`.
 *
 * `kind` discriminator:
 *   - stop      — przystanek (default)
 *   - pickup    — dodatkowy odbiór (np. koń z innej stajni)
 *   - dropoff   — dodatkowy zwrot
 *   - rest      — postój kierowcy (czas reagulator UE)
 *   - poi       — z biblioteki POI transportera (poi_id snapshot)
 *
 * `poi_id` snapshot (nullable) gdy waypoint pochodzi z pois library;
 * pozwala na change-tracking gdy POI z biblioteki zostanie usunięty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_waypoints', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('quote_id')
                ->constrained('quotes')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('kind', 16)->default('stop')->index();

            $table->string('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->ulid('poi_id')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Sortowanie w kolejności tworzenia per quote — index ułatwia
            // ORDER BY przy listingu.
            $table->index(['quote_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_waypoints');
    }
};
