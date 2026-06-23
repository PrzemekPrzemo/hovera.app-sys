<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel B (epic 1.3) — wątki wiadomości stajnia ↔ external specialist.
 *
 * Threading żyje w central DB (cross-tenant): jeden specjalista może mieć
 * wątki z wieloma stajniami. Każdy wątek należy do dokładnie jednej pary
 * (tenant, specialist) i opcjonalnie dotyczy konkretnego konia.
 *
 * `horse_id` to soft string FK — konie żyją w tenant DB (osobne połączenie),
 * więc brak FK constraint, podobnie jak inne cross-DB referencje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('specialist_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('specialist_id')->constrained('external_specialists')->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Opcjonalny kontekst konia (tenant DB) — soft ref, bez constraint.
            $table->string('horse_id', 26)->nullable()->index();

            $table->string('subject', 200);

            // Denormalizacja do sortowania listy wątków bez agregacji messages.
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // Lista wątków danej stajni / danego specjalisty.
            $table->index(['tenant_id', 'specialist_id'], 'specialist_threads_tenant_specialist_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('specialist_threads');
    }
};
