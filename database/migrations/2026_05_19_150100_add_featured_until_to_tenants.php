<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsored placements (faza 1): expirable featured boost. Master admin
 * może wystawić wyróżnienie na czas określony (30/60/90 dni) przez
 * `AddonPurchase` — webhook P24/PayU flipnie `is_featured=true` +
 * `featured_until = now()+N days`. Daily cron `transport:expire-featured`
 * cofa boost gdy `featured_until < NOW()`.
 *
 * Legacy permanent featured (gdy `featured_until IS NULL` ale
 * `is_featured=true`) zostają — dla manualnie ustawionych przez admina
 * przed wdrożeniem sponsorship'u.
 *
 * Patrz docs/TRANSPORT.md §16 (sponsored placements).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->timestamp('featured_until')->nullable()->after('featured_at');
            // Index dla daily cron `WHERE is_featured=true AND featured_until < NOW()`.
            $table->index(['is_featured', 'featured_until']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'featured_until']);
            $table->dropColumn('featured_until');
        });
    }
};
