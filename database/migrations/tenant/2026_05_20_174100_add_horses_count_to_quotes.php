<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot per-quote: liczba koni + zamrożona stawka za dodatkowego konia.
 *
 * Snapshot stawki (`extra_horse_fee_snapshot`) chroni przed efektem
 * zmiany TransportSettings po wystawieniu oferty — quote zachowuje
 * wartość z momentu wyceny, identycznie jak `rate_per_km`, `fuel_*` itp.
 *
 * `horses_count` default=1 zachowuje stary behaviour wycen sprzed PR-a;
 * `unsignedTinyInteger` daje zakres 0–255, więcej niż realnie potrzeba
 * (UI walidacja 1–30).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedTinyInteger('horses_count')->default(1)->after('loaded');
            $table->decimal('extra_horse_fee_snapshot', 8, 2)->default(0)->after('fuel_surcharge');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['horses_count', 'extra_horse_fee_snapshot']);
        });
    }
};
