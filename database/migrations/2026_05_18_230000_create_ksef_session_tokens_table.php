<?php

declare(strict_types=1);

use App\Domain\Transport\Ksef\Session\KsefSessionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache krótko-żyjących SessionToken'ów KSeF per (tenant, środowisko).
 *
 * Pełny KSeF handshake jest drogi: AuthorisationChallenge → RSA-OAEP
 * (encrypt token + timestamp) → POST InitToken → SessionToken (TTL ~3h).
 * Bez cachu każdy `submit` faktury wykonywałby cały ten ciąg, co dla
 * batcha 50 faktur to 50 razy 3 round-tripy do MF. Nieakceptowalne.
 *
 * Trzymamy zatem session token + użyty AES-256 klucz (ten sam musi
 * być użyty do szyfrowania payloadów faktur w danej sesji) zaszyfrowane
 * przez Laravel Crypt::encryptString. Po expirym ponawiamy handshake.
 *
 * Tabela jest w CENTRAL DB (nie tenant) bo to dane operacyjne integracji
 * — patrz analogicznie central audit log. Pozwala też batch'om
 * cross-tenant (cron poll) szybko sprawdzić cache bez przełączania DB.
 *
 * Unique (tenant_id, environment) — jeden aktywny session per środowisko
 * (test/demo/production traktujemy osobno bo ich tokeny nie są wymienne).
 *
 * @see KsefSessionManager
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksef_session_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('environment', 16);
            $table->text('session_token_encrypted');
            $table->text('aes_key_encrypted');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'environment'], 'ksef_session_tokens_tenant_env_unique');
            $table->index('expires_at', 'ksef_session_tokens_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_session_tokens');
    }
};
