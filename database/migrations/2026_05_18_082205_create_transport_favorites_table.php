<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ulubieni transporterzy — patrz docs/TRANSPORT.md §4 + §5.1.
 *
 * Owner stajni może oznaczyć do N transporterów jako "ulubieni" (limit per
 * tenant ustalimy w UI; obecnie OP3 → "5 lub bez limitu" jest decyzją otwartą).
 * Tryb DIRECT składania leadu pre-fill'uje wybór z tej listy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transport_favorites', function (Blueprint $table) {
            $table->id();
            $table->ulid('stable_tenant_id')->nullable()->index();   // jeśli oznacza stajnia jako tenant
            $table->ulid('user_id')->nullable()->index();             // jeśli oznacza pojedynczy user (anonim z konta)
            $table->ulid('transporter_tenant_id')->index();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // Można oznaczyć ulubionego raz per (tenant|user, transporter).
            $table->unique(['stable_tenant_id', 'transporter_tenant_id'], 'transport_favorites_tenant_unique');
            $table->unique(['user_id', 'transporter_tenant_id'], 'transport_favorites_user_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transport_favorites');
    }
};
