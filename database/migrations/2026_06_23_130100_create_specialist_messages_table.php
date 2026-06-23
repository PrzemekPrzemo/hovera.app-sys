<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel B (epic 1.3) — pojedyncze wiadomości w wątku Channel B.
 *
 * `sender_type` rozróżnia kto napisał:
 *   - 'tenant_user' → sender_id = central user id (pracownik stajni)
 *   - 'specialist'  → sender_id = external_specialists id
 *
 * `read_at` = kiedy DRUGA strona odczytała wiadomość (jednowymiarowe — wątek
 * jest 1:1, więc wystarczy jeden znacznik per wiadomość).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('specialist_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('specialist_threads')->cascadeOnDelete();

            // 'tenant_user' | 'specialist'
            $table->string('sender_type', 16);
            // central user id LUB external_specialists id (zależnie od typu).
            $table->string('sender_id', 26);

            $table->text('body');
            $table->json('attachments')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'created_at'], 'specialist_messages_thread_created_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('specialist_messages');
    }
};
