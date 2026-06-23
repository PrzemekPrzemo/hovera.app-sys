<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel D (epic 3) — bezpośrednie wątki właściciel konia ↔ external
 * specialist. Cross-tenant: owner i specjalista nie muszą należeć do tej
 * samej stajni.
 *
 * `horse_id` = jawnie udostępniony koń w wątku (per captured decisions §4:
 * vet widzi tylko konia explicit shared, NIE wszystkie konie właściciela).
 * Soft string ref do central horse registry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('owner_specialist_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('specialist_id')->constrained('external_specialists')->cascadeOnDelete();

            $table->string('horse_id', 26)->nullable()->index();
            $table->string('subject', 200);
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'specialist_id'], 'owner_specialist_threads_owner_specialist_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('owner_specialist_threads');
    }
};
