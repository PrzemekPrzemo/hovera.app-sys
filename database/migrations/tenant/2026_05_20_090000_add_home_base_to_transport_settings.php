<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `home_address` + lat/lng dla bazy transportera. Używane przez tryb
 * kalkulacji `return_home` — silnik wyceny dolicza kilometry powrotu
 * z dropoff do bazy.
 *
 * Wszystkie 3 pola nullable — istnieje tenant bez ustawionej bazy (np.
 * świeży tenant po rejestracji), calculator wtedy soft-fallback'uje na
 * RoundTrip z notification "ustaw bazę by używać return_home".
 *
 * Patrz docs/TRANSPORT.md §4.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->string('home_address', 255)->nullable()->after('currency');
            $table->decimal('home_lat', 10, 7)->nullable()->after('home_address');
            $table->decimal('home_lng', 10, 7)->nullable()->after('home_lat');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn(['home_address', 'home_lat', 'home_lng']);
        });
    }
};
