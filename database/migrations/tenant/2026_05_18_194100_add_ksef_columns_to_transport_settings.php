<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-transporter KSeF — patrz docs/TRANSPORT.md §12 i §14.1.
 *
 * Hovera NIE jest wystawcą faktur transportowych (§12 marketplace
 * positioning). Każdy transporter konfiguruje WŁASNY token autoryzacyjny
 * KSeF (zdobyty w MF), własny NIP, własne środowisko (test / prod).
 * Hovera robi tylko passthrough — UI + walidacja + audit + retry.
 *
 * Token przechowujemy SZYFROWANY (Laravel Crypt::encryptString) — nawet
 * wewnętrzny snapshot bazy nie powinien zdradzać tokenów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            // KSeF: per-transporter credentials. Nazwy zgodne z konwencją
            // pozostałych zaszyfrowanych pól w hovery (suffix _encrypted).
            $table->text('ksef_token_encrypted')->nullable()->after('routing_provider');
            $table->string('ksef_environment', 16)->default('test')->after('ksef_token_encrypted');
            $table->string('ksef_nip', 16)->nullable()->after('ksef_environment');
            $table->boolean('ksef_enabled')->default(false)->after('ksef_nip');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn(['ksef_token_encrypted', 'ksef_environment', 'ksef_nip', 'ksef_enabled']);
        });
    }
};
