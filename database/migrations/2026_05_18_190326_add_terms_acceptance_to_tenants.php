<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit-trail dla akceptacji regulaminu przy signupie. Dwa pola:
 *
 *   terms_accepted_at  — timestamp pierwszej akceptacji (signup)
 *   terms_version      — wersja regulaminu (config('hovera.legal.terms_version'),
 *                        format YYYY-MM) — pozwala wykryć tenantów,
 *                        którzy zaakceptowali starszą wersję i wymagają
 *                        ponownej zgody po istotnej zmianie regulaminu.
 *
 * Dodatkowy szczegółowy ślad (IP, user-agent, akceptujący e-mail) trafia
 * do audit_log_master przez MasterAuditLogger::record('tenant.terms_accepted').
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
            $table->timestamp('terms_accepted_at')->nullable()->after('verification_notes');
            $table->string('terms_version', 16)->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};
