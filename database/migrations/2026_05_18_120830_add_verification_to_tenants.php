<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weryfikacja konta transportera przez master admin'a. Stable tenant'y
 * mają to pole NULL (irrelevant) — pasywne dla istniejących stajni.
 *
 * Status default 'pending' tylko dla nowo tworzonych transporterów —
 * istniejące tenanty (wszystkie stable bo wcześniej brak transporterów)
 * pozostają z NULL.
 *
 * Patrz docs/TRANSPORT.md (verification flow dorobiony po feedbacku produkcyjnym).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->enum('verification_status', ['pending', 'under_review', 'verified', 'rejected'])
                ->nullable()
                ->after('type');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->ulid('verified_by_user_id')->nullable()->after('verified_at');
            $table->text('verification_notes')->nullable()->after('verified_by_user_id');

            $table->index(['type', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropIndex(['type', 'verification_status']);
            $table->dropColumn(['verification_status', 'verified_at', 'verified_by_user_id', 'verification_notes']);
        });
    }
};
