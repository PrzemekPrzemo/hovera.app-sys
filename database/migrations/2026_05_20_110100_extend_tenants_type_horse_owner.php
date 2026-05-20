<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konwertuje `tenants.type` z ENUM('stable','transporter') na VARCHAR(32).
 *
 * Powód: każdy nowy `TenantType` (HorseOwner i przyszłe) wymagałby kolejnej
 * `ALTER TABLE MODIFY ENUM`, MySQL-specyficznej. VARCHAR + walidacja po
 * stronie aplikacji (TenantType enum cast) = zero friction przy nowych typach.
 *
 * Używamy `Schema::table()->change()` — Laravel via doctrine/dbal obsługuje
 * to driver-agnostycznie (MySQL: MODIFY COLUMN, SQLite: table rebuild
 * z zachowaniem indexów i FK).
 *
 * Patrz `App\Enums\TenantType::HorseOwner`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function ($table) {
            $table->string('type', 32)->default('stable')->change();
        });
    }

    public function down(): void
    {
        if (DB::connection('central')->getDriverName() === 'sqlite') {
            return;
        }

        DB::connection('central')
            ->table('tenants')
            ->where('type', 'horse_owner')
            ->delete();

        DB::connection('central')->statement(
            "ALTER TABLE tenants MODIFY COLUMN type ENUM('stable','transporter') NOT NULL DEFAULT 'stable'"
        );
    }
};
