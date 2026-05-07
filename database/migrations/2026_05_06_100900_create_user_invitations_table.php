<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email');

            // Tenant-scoped invitation (membership) or generic (just to
            // create a master admin account, future use).
            $table->foreignUlid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('role', 32)->nullable();
            $table->string('name')->nullable();

            // SHA-256 of a 40-char random URL-safe token. The plaintext
            // token only ever leaves the system as part of the email link.
            $table->char('token_hash', 64)->unique();

            $table->foreignUlid('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            $table->index(['email', 'accepted_at']);
            $table->index(['tenant_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
