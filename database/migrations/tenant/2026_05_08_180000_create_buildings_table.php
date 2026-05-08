<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buildings — opcjonalne ugrupowanie boksów per budynek. Stajnia
 * (jako miejsce) może mieć kilka budynków: "Stajnia czerwona",
 * "Stajnia nowa", "Stajnia letnia", padoki w różnych częściach.
 *
 * box.building_id jest nullable — istniejące boxy nie wymagają
 * przypisania (default group "Bez budynku"). Owner przy okazji
 * uporządkowuje stary stan, lub zostawia jak jest.
 *
 * Brak twardych limitów — owner sam decyduje ile budynków i ile
 * boksów; limity koni / klientów (z planu) wciąż obowiązują na
 * poziomie tenanta, nie boksu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->ulid('building_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropColumn('building_id');
        });
        Schema::dropIfExists('buildings');
    }
};
