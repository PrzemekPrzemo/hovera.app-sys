<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of every back-office message a master admin sends to
 * a tenant's owners/admins from /admin/tenants/{id}/mailer. The body is
 * stored verbatim (Markdown) so the support team can reconstruct what
 * was actually delivered when a tenant disputes "I never got that".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('tenant_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('template', 64)->default('custom');
            $table->string('subject', 255);
            $table->text('body');
            $table->unsignedSmallInteger('recipients_count')->default(0);
            $table->json('recipients')->nullable(); // emails snapshot
            $table->timestamp('sent_at')->useCurrent();

            $table->index(['tenant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_messages');
    }
};
