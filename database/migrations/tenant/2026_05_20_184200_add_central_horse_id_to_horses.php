<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft FK z lokalnej projekcji konia (tenant.horses) do central rejestru
 * (central.central_horse_registry.id). Patrz docs/MARKETPLACE-ROADMAP.md
 * PR 4/5.
 *
 * NULL OK — istniejące stable horses bez owner accounta nie mają jeszcze
 * central rejestru. Stable owner może claim'ować owner'a przez invite
 * (PR 4/5 follow-up), co później back-fillsuje central_horse_id.
 *
 * Foreign key constraint NIE jest stosowany — central jest fizycznie
 * inna baza, MySQL nie obsługuje cross-database FK. Integrity sprawdzamy
 * w aplikacji (HorseRegistrySyncService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->ulid('central_horse_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->dropIndex(['central_horse_id']);
            $table->dropColumn('central_horse_id');
        });
    }
};
