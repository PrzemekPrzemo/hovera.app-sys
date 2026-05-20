<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POI library transportera — patrz docs/MARKETPLACE-ROADMAP.md
 * "Waypoints + reorder + POI library".
 *
 * Reuse'owalne lokalizacje (baza transportera, stajnie z którymi
 * współpracuje, parkingi, stacje paliw). User klika w Calculator/
 * QuoteResource i wybiera POI zamiast wpisywać adres ręcznie.
 *
 * `kind` discriminator:
 *   - base    — własna baza transportera
 *   - stable  — stajnia (klient)
 *   - parking — parking truck-friendly
 *   - fuel    — stacja paliw
 *   - other   — generic
 *
 * Soft-deletes — POI z snapshot'em na quote (waypoint.poi_id) chcemy
 * zachować nawet po usunięciu z biblioteki, ale ukrytego z listy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pois', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 120);
            $table->string('kind', 16)->default('other')->index();

            $table->string('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pois');
    }
};
