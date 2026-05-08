<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HOTFIX: kolumna `horses.sex` była ENUM z wartościami
 * ['mare','stallion','gelding','filly','colt','foal']. Po PR #105
 * dodaliśmy 'breeding_stallion' (Ogier kryjący) — ENUM tego nie
 * akceptuje, więc zapis konia z tą wartością wywala 1265 Data
 * truncated.
 *
 * Zmieniamy ENUM → VARCHAR(32). Krótka migracja, brak utraty danych.
 * Ufamy że aplikacja sama waliduje wartości (Filament Select +
 * model casts), nie potrzebujemy enforce'u w DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB składnia. Schema::table->string nie potrafi
        // zmienić ENUM → VARCHAR bez doctrine/dbal, więc raw SQL.
        DB::connection('tenant')->statement(
            'ALTER TABLE `horses` MODIFY COLUMN `sex` VARCHAR(32) NULL',
        );
    }

    public function down(): void
    {
        // No-op — wracanie do węższego ENUM straciłoby wartości
        // 'breeding_stallion' już zapisane.
    }
};
