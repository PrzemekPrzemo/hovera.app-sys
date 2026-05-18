<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pojazdy w bazie transportera. Patrz docs/TRANSPORT.md §4.3.
 *
 * Migracja per-tenant — uruchamiana tylko dla tenant'ów typu transporter
 * (typowi stajenni nie potrzebują tabeli, ale obecność pustej tabeli nikomu
 * nie szkodzi; gating typu jest na poziomie panelu i resource'a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 120);                          // np. "Volvo FH16 — wóz duży"
            $table->string('registration_plate', 16)->index();
            $table->unsignedTinyInteger('capacity_horses');       // ile koni mieści
            $table->decimal('gross_weight_kg', 8, 0)->nullable(); // DMC
            $table->decimal('payload_kg', 8, 0)->nullable();      // ładowność
            $table->unsignedSmallInteger('year_of_manufacture')->nullable();

            $table->json('photos')->nullable();                   // public storage paths

            $table->boolean('has_air_suspension')->default(false);
            $table->boolean('has_camera')->default(false);
            $table->boolean('has_climate_control')->default(false);

            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
