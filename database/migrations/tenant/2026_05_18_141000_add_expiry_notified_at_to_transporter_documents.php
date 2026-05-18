<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `expiry_notified_at` — idempotencja powiadomienia o wygaśnięciu dokumentu.
 *
 * Reguła: TransporterDocumentsExpiryNotifyCommand wysyła mail na 30 dni przed
 * `expires_at` tylko wtedy, gdy `expiry_notified_at IS NULL` lub jest dawniejsze
 * niż dzień zmiany `expires_at` (re-upload resetuje notify).
 *
 * Patrz docs/TRANSPORT.md §1 (onboarding) — expiry watchdog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transporter_documents', function (Blueprint $table) {
            $table->timestamp('expiry_notified_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('transporter_documents', function (Blueprint $table) {
            $table->dropColumn('expiry_notified_at');
        });
    }
};
