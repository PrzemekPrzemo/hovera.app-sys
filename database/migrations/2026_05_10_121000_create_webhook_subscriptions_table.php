<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant outbound webhook subscriptions. Tenants register URLs to be
 * notified about domain events (invoice.paid, booking.created, etc.) — the
 * dispatcher fans out to all matching subscriptions and queues delivery jobs.
 *
 * `secret` is used as the HMAC SHA256 signing key (Stripe-compatible header
 * `X-Hovera-Signature: sha256=<hex>`). It's stored plaintext on purpose —
 * receivers compute their own HMAC against the raw body and compare; we
 * have to be able to recover it server-side to sign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('webhook_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('url', 500);
            $table->json('events');
            $table->string('secret', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_delivery_at')->nullable();
            $table->string('last_delivery_status', 32)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('webhook_subscriptions');
    }
};
