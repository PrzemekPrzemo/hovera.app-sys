<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel D (epic 3) — wiadomości w wątku właściciel ↔ specjalista.
 *
 * `sender_type`:
 *   - 'owner'      → sender_id = central user id (właściciel konia)
 *   - 'specialist' → sender_id = external_specialists id
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('owner_specialist_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('owner_specialist_threads')->cascadeOnDelete();

            $table->string('sender_type', 16);
            $table->string('sender_id', 26);

            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'created_at'], 'owner_specialist_messages_thread_created_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('owner_specialist_messages');
    }
};
