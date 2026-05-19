<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Embed snippet config dla transporterów — trzymamy w central `tenants`,
 * NIE w per-tenant `transport_settings`. Powody:
 *   - CORS middleware uruchamia się przed switching DB connection — i tak
 *     musimy resolve'ować tenant po slug'u w central (LookupBySlug pattern).
 *   - Spójne z `tenants.settings.payments.p24` (creds też w central) —
 *     reauth/CORS gates są tylko gateowanie dostępu do tenanta, więc nie
 *     wymagają tenant DB lookup.
 *   - Brak side-effects per-tenant DB connection w testach.
 *
 * Patrz docs/TRANSPORT.md §16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            // JSON array stringów (origins). Pusty/null = embed wyłączony.
            $table->json('embed_allowed_origins')
                ->nullable()
                ->after('featured_by_user_id');

            // Encrypted opaque token (hex). Cast 'encrypted' szyfruje przy save,
            // dekoduje przy read. Regenerable z UI (invalidates old).
            $table->text('embed_api_token')
                ->nullable()
                ->after('embed_allowed_origins');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['embed_allowed_origins', 'embed_api_token']);
        });
    }
};
