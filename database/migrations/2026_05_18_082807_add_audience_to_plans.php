<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discriminator publiczności planu — odpowiednik `tenants.type` na poziomie
 * cennika. Stajnia widzi tylko plany `audience=stable`, transporter tylko
 * `audience=transporter`. Patrz docs/TRANSPORT.md §2 D2.
 *
 * Nie zmieniamy unique constraint na `code` — nazewnictwo planów rozróżnia
 * publiczność (np. `solo` to plan stajenny, `transport_solo` transportowy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->enum('audience', ['stable', 'transporter'])
                ->default('stable')
                ->after('code');
        });

        DB::connection('central')
            ->table('plans')
            ->whereNull('audience')
            ->update(['audience' => 'stable']);

        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->index(['audience', 'is_active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->dropIndex(['audience', 'is_active', 'is_public']);
            $table->dropColumn('audience');
        });
    }
};
