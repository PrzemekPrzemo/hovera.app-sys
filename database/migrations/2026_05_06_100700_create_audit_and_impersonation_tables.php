<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Append-only audit log of every master admin action.
        Schema::create('audit_log_master', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 128)->index();
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->foreignUlid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['target_type', 'target_id']);
        });

        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('master_user_id')->constrained('users');
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->foreignUlid('target_user_id')->nullable()->constrained('users');
            $table->text('reason');     // mandatory justification for RODO
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();

            $table->index(['master_user_id', 'started_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 128)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('impersonation_sessions');
        Schema::dropIfExists('audit_log_master');
    }
};
