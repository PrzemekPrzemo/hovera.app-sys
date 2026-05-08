<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARMiR registration: właściciele koni mają EP (numer producenta nadany
 * przez ARMiR przy rejestracji konia w Centralnej Bazie Koniowatych).
 * Jeśli ktoś nie ma EP → używamy PESEL.
 *
 * Dwa osobne pola (zamiast jednego "ARMiR_OR_PESEL") — różne formaty,
 * różne reguły walidacji w przyszłości, a w UI i tak pokazujemy obok
 * siebie z helperem "Wpisz EP, jeśli nie ma — PESEL".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('armir_producer_id', 32)->nullable()->after('tax_id');
            $table->string('pesel', 11)->nullable()->after('armir_producer_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['armir_producer_id', 'pesel']);
        });
    }
};
