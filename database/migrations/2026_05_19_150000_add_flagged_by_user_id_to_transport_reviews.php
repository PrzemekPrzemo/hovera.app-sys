<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit who z tenant'a flagował review. Wcześniej mieliśmy tylko
 * `flagged_by_tenant_at` timestamp, ale przy tenants z wieloma user'ami
 * (owner/admin/manager) trzeba wiedzieć który user kliknął flag —
 * dla audit log + przyszłej obrony przed flag-abuse'em.
 *
 * Patrz docs/TRANSPORT.md §12 (reviews moderation).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('transport_reviews', function (Blueprint $table) {
            $table->ulid('flagged_by_user_id')->nullable()->after('flagged_by_tenant_at');
            $table->index(['transporter_tenant_id', 'flagged_by_tenant_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('transport_reviews', function (Blueprint $table) {
            $table->dropIndex(['transporter_tenant_id', 'flagged_by_tenant_at']);
            $table->dropColumn('flagged_by_user_id');
        });
    }
};
