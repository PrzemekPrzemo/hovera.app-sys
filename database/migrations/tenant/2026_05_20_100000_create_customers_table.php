<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baza klientów transportera (per-tenant DB) — przeszukiwana przy tworzeniu
 * oferty, pozwala dodawać własnych klientów i weryfikować w MF/KRS/CEIDG.
 *
 * Pola `last_verified_at` + `verification_data` (jsonb): raw response z
 * Białej Listy MF lub KRS, cache'owany żeby nie pytać przy każdym otwarciu
 * Customer page'a.
 *
 * Patrz docs/TRANSPORT.md §3.6 (planowane) + user feedback "weryfikacja
 * danych w gus, krs, ceidg".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 255)->index();
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('company', 255)->nullable()->index();
            $table->string('tax_id', 32)->nullable()->index();
            $table->string('krs_number', 16)->nullable();
            $table->text('address')->nullable();

            // Source: skąd klient trafił do bazy (lead acceptance, manual, import).
            $table->string('source', 32)->default('manual')->index();

            // Weryfikacja w MF Biała Lista / KRS / CEIDG — cache'owany payload.
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_source', 16)->nullable();   // 'mf', 'krs', 'ceidg'
            $table->json('verification_data')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
