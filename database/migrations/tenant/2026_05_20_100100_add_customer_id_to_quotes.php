<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `customer_id` na ofercie — FK do `customers` (nullable). Quota nadal
 * trzyma snapshot `customer_*` w swoich polach (historyczna dokładność —
 * zmiana danych klienta nie zmienia poprzednich ofert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->ulid('customer_id')->nullable()->after('customer_address')->index();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
