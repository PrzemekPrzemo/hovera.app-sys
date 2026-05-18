<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discriminator dla tenant'a: stable vs transporter. Patrz docs/TRANSPORT.md §3.1.
 *
 * Domyślna wartość 'stable' zapewnia, że istniejące tenanty pozostają poprawne
 * (wszystkie obecne tenanty to stajnie — moduł transportu jeszcze nie ruszył).
 * Backfill to formalność dla wierszy utworzonych przed wymuszeniem default'u.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->enum('type', ['stable', 'transporter'])
                ->default('stable')
                ->after('legal_name');
        });

        // Defensywny backfill — w nowych instalacjach default sam załatwi sprawę,
        // ale w środowiskach z istniejącymi danymi gwarantujemy explicit value.
        DB::connection('central')
            ->table('tenants')
            ->whereNull('type')
            ->update(['type' => 'stable']);

        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn('type');
        });
    }
};
