<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `vehicle_type` discriminator dla pojazdów transportera.
 *
 *  - 'truck'   : pojazd z silnikiem (ciężarówka, van, samochód osobowy).
 *                Ma spalanie, może ciągnąć przyczepę.
 *  - 'trailer' : przyczepa do koni (bez silnika). Brak spalania —
 *                w ofercie wymaga truck'a jako pojazdu prowadzącego.
 *
 * Default 'truck' bo wszystkie istniejące pojazdy w bazie były domyślnie
 * traktowane jako pełne pojazdy z silnikiem.
 *
 * Patrz docs/TRANSPORT.md §4.3 + Enum App\Enums\VehicleType.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_type', 16)
                ->default('truck')
                ->after('name')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['vehicle_type']);
            $table->dropColumn('vehicle_type');
        });
    }
};
