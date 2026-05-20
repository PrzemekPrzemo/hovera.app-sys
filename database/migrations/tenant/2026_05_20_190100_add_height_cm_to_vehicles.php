<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wysokość pojazdu w cm — opcjonalna, używana przez routing HGV
 * (ORS profile_params.restrictions.height) gdy ustawiona.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md "ORS routing z weight/height pojazdu".
 *
 * Pole nullable — istniejący transporterzy nie muszą uzupełniać,
 * routing wtedy fallback'uje na default ORS HGV (bez restriction'ów
 * wysokości). Z koniem w ladowarce typowo: 3.5–4.0 m (350–400 cm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('height_cm')->nullable()->after('gross_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('height_cm');
        });
    }
};
