<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central tabela — ulubione trasy ownera (np. "Stajnia → Klinika w
 * Warszawie"). Owner zapisuje template w OrderTransport po raz pierwszy
 * z label'em; potem wybiera z dropdownu i form pre-filluje pickup/dropoff/
 * notes (geocode jest re-run przy submit żeby zaktualizować voivodeship'y).
 *
 * `default_horse_central_id` — opcjonalne, gdy owner zwykle wozi tego
 * samego konia tą trasą (klinika weterynaryjna, zawody itd.). Soft FK
 * do CentralHorseRegistry (bez constrained — koń może być usunięty,
 * trasa pozostaje).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        if (Schema::hasTable('owner_favorite_routes')) {
            return;
        }

        Schema::create('owner_favorite_routes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('pickup_address', 500);
            $table->string('dropoff_address', 500);
            $table->text('notes')->nullable();
            $table->string('default_horse_central_id', 26)->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'label'], 'ofr_owner_label_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_favorite_routes');
    }
};
