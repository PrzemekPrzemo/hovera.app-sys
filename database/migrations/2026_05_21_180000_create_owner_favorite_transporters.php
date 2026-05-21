<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central tabela — ulubieni przewoźnicy ownera. Owner zaznacza swoich
 * zaufanych transporterów; przy "Zamów transport" może wybrać "Wyślij
 * tylko do moich ulubionych" → lead.mode=targeted z list'ą tych ID'ów.
 *
 * Unique constraint (owner_user_id, transporter_tenant_id) zapobiega
 * duplikatom (kliknięcie "dodaj ulubionego" dwukrotnie = idempotent).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md (future enhancements section).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        if (Schema::hasTable('owner_favorite_transporters')) {
            return;
        }

        Schema::create('owner_favorite_transporters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('transporter_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['owner_user_id', 'transporter_tenant_id'], 'oft_unique_per_owner');
            $table->index('transporter_tenant_id', 'oft_transporter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_favorite_transporters');
    }
};
