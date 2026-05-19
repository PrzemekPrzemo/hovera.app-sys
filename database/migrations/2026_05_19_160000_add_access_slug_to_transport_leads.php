<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent per-lead access token. Klient publiczny po wypełnieniu
 * `/transport/zapytanie` dostaje email z linkiem `/transport/zapytanie/{slug}`,
 * gdzie widzi swoje zapytanie + napływające oferty. Slug to UUID v4 —
 * długi i nieprzewidywalny, działa permanentnie (do momentu revoke).
 *
 * Idempotentne guard'y per handover §6 gotcha #1.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('transport_leads', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('transport_leads', 'access_slug')) {
                $table->uuid('access_slug')->nullable()->unique()->after('id');
            }
            if (! Schema::connection('central')->hasColumn('transport_leads', 'access_revoked_at')) {
                $table->timestamp('access_revoked_at')->nullable()->after('access_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('transport_leads', function (Blueprint $table) {
            if (Schema::connection('central')->hasColumn('transport_leads', 'access_revoked_at')) {
                $table->dropColumn('access_revoked_at');
            }
            if (Schema::connection('central')->hasColumn('transport_leads', 'access_slug')) {
                $table->dropUnique(['access_slug']);
                $table->dropColumn('access_slug');
            }
        });
    }
};
