<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel C (epic 2) — wewnętrzne kanały komunikacji stajni
 * (Slack-like): #general, #weterynaria, #transport auto-tworzone +
 * admin może dodać własne.
 *
 * Wszystko w tenant DB (per-stable). `user_id` / `author_user_id` /
 * `created_by_user_id` to soft string FK do central.users (osobne
 * połączenie, bez constraint) — tak jak `central_user_id` w innych
 * tabelach tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();

            // Auto-tworzone kanały (#general itd.) — nie można ich usunąć w UI.
            $table->boolean('is_default')->default(false)->index();

            $table->string('created_by_user_id', 26)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('internal_channel_members', function (Blueprint $table) {
            $table->foreignUlid('channel_id')->constrained('internal_channels')->cascadeOnDelete();
            $table->string('user_id', 26);

            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('notifications_enabled')->default(true);
            // Znacznik ostatniego odczytu — podstawa unread badge + digestu.
            $table->timestamp('last_read_at')->nullable();

            $table->primary(['channel_id', 'user_id']);
        });

        Schema::create('internal_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('channel_id')->constrained('internal_channels')->cascadeOnDelete();
            $table->string('author_user_id', 26);

            $table->text('body');
            $table->json('attachments')->nullable();
            // Lista central user id wspomnianych przez @mention (do notyfikacji).
            $table->json('mentions')->nullable();

            $table->timestamps();

            $table->index(['channel_id', 'created_at'], 'internal_messages_channel_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_messages');
        Schema::dropIfExists('internal_channel_members');
        Schema::dropIfExists('internal_channels');
    }
};
