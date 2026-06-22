<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel B — magic link tokens dla external specialists.
 *
 * Lifecycle:
 *   - Stable klika "Zaproś weterynarza" → tworzy ExternalSpecialist row
 *     (jeśli email nowy) + SpecialistMagicLink z kind='initial_setup'
 *   - Vet klika link w mailu → /specialist/setup/{token}
 *   - Ustawia hasło + dostaje email verification code (osobny mail)
 *   - Wpisuje code → `email_verified_at` na ExternalSpecialist
 *   - Po setup'ie używa login flow (email + password)
 *
 * Token storage: tylko hash (sha256). Plain token NIGDY nie żyje w DB.
 * Plain token w URL = jednorazowy show (po click'u zostaje hash w DB +
 * `used_at` timestamp).
 *
 * Per captured decisions §3:
 *   - 7d expiry dla initial_setup link
 *   - password_reset i login używają tej samej tabeli z różnym `kind`
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('specialist_magic_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('specialist_id')->constrained('external_specialists')->cascadeOnDelete();

            // SHA256 hex (64 chars) raw token. Plain token zwracany do
            // mailera raz, potem nie żyje już nigdzie.
            $table->string('token_hash', 64)->index();

            // 'initial_setup' (po invite), 'password_reset', 'login'
            // (passwordless future option).
            $table->string('kind', 32);

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            // IP użytkownika gdy generowano link (audit), nullable bo
            // niektóre invite są z cron / API.
            $table->string('issued_from_ip', 45)->nullable();

            // Jaki tenant zlecił invite (audit — który stable zaprosił).
            $table->foreignUlid('issued_for_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->timestamps();

            // Cleanup expired links — composite index (kind, expires_at).
            $table->index(['kind', 'expires_at'], 'specialist_magic_links_kind_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('specialist_magic_links');
    }
};
