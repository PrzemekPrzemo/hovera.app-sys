<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `trailer_id` na ofercie — opcjonalna przyczepa kombinowana z pojazdem
 * prowadzącym (`vehicle_id`).
 *
 * Konwencja semantyczna (gating w UI/formie, nie na DB-level żeby uniknąć
 * cross-table FK validation):
 *   - `vehicle_id`  → Vehicle z `vehicle_type='truck'`
 *   - `trailer_id`  → Vehicle z `vehicle_type='trailer'`
 *
 * Bez osobnej tabeli `trailers` — to dalej Vehicle, tylko innym typem.
 * Patrz docs/TRANSPORT.md §4.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->ulid('trailer_id')->nullable()->after('vehicle_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['trailer_id']);
            $table->dropColumn('trailer_id');
        });
    }
};
