<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-tenant relacja "koń X boarduje w stajni Y" — patrz
 * docs/MARKETPLACE-ROADMAP.md PR 4/5.
 *
 * Lifecycle:
 *   pending  → request złożony (owner zapytał, stable jeszcze nie odpowiedział)
 *   active   → stable zaakceptował (started_at = now)
 *   ended    → boarding zakończony (ended_at = now). Owner przeprowadził
 *              konia do innej stajni.
 *   disputed → konflikt (np. stable claim'uje aktywny boarding, owner go
 *              już zakończył). Admin manual review.
 *
 * Unique constraint (central_horse_id, stable_tenant_id, status) zapobiega
 * duplikatom — np. jeden aktywny boarding per (horse, stable). Możemy
 * mieć poprzednie 'ended' rows historycznie + jeden 'active'/'pending'.
 *
 * `owner_user_id` NULL OK: stable ma horse'a bez przypisanego owner
 * account'a (legacy/school horse). Po invite + rejestracji owner'a
 * system back-fillsuje owner_user_id i optionally tworzy central_horse_registry
 * row (jeśli stable horse jeszcze nie miał central_horse_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('horse_boarding_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('central_horse_id')
                ->constrained('central_horse_registry')
                ->cascadeOnDelete();
            $table->ulid('stable_tenant_id')->index();
            $table->ulid('owner_user_id')->nullable()->index();

            $table->enum('status', ['pending', 'active', 'ended', 'disputed'])
                ->default('pending')
                ->index();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            $table->timestamps();

            // Jeden boarding per (horse, stable, status). Pozwala na
            // historię (ended) + current (active/pending), ale zapobiega
            // duplikatowi w tym samym statusie.
            $table->unique(['central_horse_id', 'stable_tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('horse_boarding_assignments');
    }
};
