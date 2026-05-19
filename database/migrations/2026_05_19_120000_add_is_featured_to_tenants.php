<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual "featured" boost dla rankingowania publicznego (Top 10 + /przewoznicy).
 * Master admin flipuje przez TransporterResource action. Sortowanie:
 *   is_featured DESC, review_avg DESC, review_count DESC, created_at DESC
 *
 * Stable tenant'y mają to pole nieaktualne (UI dla nich nie pokazuje toggle'a)
 * — domyślny false jest semantycznie neutralny. Patrz docs/TRANSPORT.md §16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('verification_notes');
            $table->timestamp('featured_at')->nullable()->after('is_featured');
            $table->ulid('featured_by_user_id')->nullable()->after('featured_at');

            // Indeks tylko po is_featured — używany w applyRatingSort jako
            // pierwszy klucz ORDER BY. Featured tenantów będzie 0-20, więc
            // selectivity jest wysoka (mała część wyniku).
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn(['is_featured', 'featured_at', 'featured_by_user_id']);
        });
    }
};
