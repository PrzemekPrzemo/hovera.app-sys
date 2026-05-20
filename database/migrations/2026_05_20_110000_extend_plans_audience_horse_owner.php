<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konwertuje `plans.audience` z ENUM('stable','transporter') na VARCHAR(32).
 * Powód: każdy nowy `TenantType` (np. `horse_owner`) wymagałby kolejnej
 * migracji `ALTER TABLE ... MODIFY ENUM`, która jest MySQL-specyficzna
 * (SQLite w testach nie wspiera). VARCHAR + walidacja w `TenantType` enum
 * w PHP = enforcement w aplikacji, zero friction przy nowych typach.
 *
 * Patrz `App\Enums\TenantType::HorseOwner`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('plans', function ($table) {
            $table->string('audience', 32)->default('stable')->change();
        });
    }

    public function down(): void
    {
        if (DB::connection('central')->getDriverName() === 'sqlite') {
            return;
        }

        DB::connection('central')
            ->table('plans')
            ->where('audience', 'horse_owner')
            ->delete();

        DB::connection('central')->statement(
            "ALTER TABLE plans MODIFY COLUMN audience ENUM('stable','transporter') NOT NULL DEFAULT 'stable'"
        );
    }
};
