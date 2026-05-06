<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // SHA-256 of the magic link token. Only the hash is persisted;
            // the raw token lives in the email URL exactly once.
            $table->string('magic_link_token_hash', 64)->nullable()->after('central_user_id');
            $table->timestamp('magic_link_expires_at')->nullable()->after('magic_link_token_hash');
            $table->timestamp('last_logged_in_at')->nullable()->after('magic_link_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['magic_link_token_hash', 'magic_link_expires_at', 'last_logged_in_at']);
        });
    }
};
