<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faza 6 PR 6.3 — Owner ↔ Stable approval flow. Stable zmienia w
 * panel'u `name`/`passport_number`/`microchip` konia — change wykrywa
 * Observer i tworzy pending request w tej tabeli. Owner widzi w panel'u
 * i może accept (no-op, change już jest) lub reject (revert).
 *
 * Central DB — request łączy 2 strony (stable proposes + owner approves),
 * nie żyje w żadnym z tenant DB. Soft FK do central_horse_registry
 * (logiczne, walidowane w aplikacji).
 *
 * Status:
 *   pending   — czeka na decyzję ownera (default)
 *   accepted  — owner zatwierdził, zmiana stays
 *   rejected  — owner odrzucił, Horse rekord cofnięty do old_value
 *
 * Idempotent (hasTable check) — bezpieczne dla rerun'a deploy'u.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 Q4".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('horse_field_change_requests')) {
            return;
        }

        Schema::create('horse_field_change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('central_horse_id', 26)->index();
            $table->string('field', 32)->index();             // 'name' | 'passport_number' | 'microchip'
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('proposed_by_tenant_id', 26)->index();
            $table->string('proposed_by_user_id', 26)->nullable();  // central user (operator stajni)
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('proposed_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by_user_id', 26)->nullable();   // owner user
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            // Per central_horse_id + field jest TYLKO 1 pending naraz
            // (jeśli stable zmienia kilka razy zanim owner zdecyduje,
            // istniejący pending request jest aktualizowany do nowego
            // new_value przez serwis).
            $table->index(['central_horse_id', 'field', 'status'], 'hfcr_horse_field_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_field_change_requests');
    }
};
