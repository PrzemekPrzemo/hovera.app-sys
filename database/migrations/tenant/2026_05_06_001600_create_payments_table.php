<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant payment ledger. One row per "client tries to pay for X" —
 * could be a calendar entry (lesson) or a pass purchase. We keep both
 * FKs nullable + one index for fast lookup either direction.
 *
 * Provider is stored as the enum's string value (p24/payu/stripe/mollie)
 * so we can introduce a new provider without a schema change. Provider
 * payload (session id, redirect url, raw webhook body) lives in JSON
 * `provider_data` — keeps the schema lean and avoids per-provider
 * migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();

            // What is being paid for — exactly one of these is set.
            // Polymorphic-soft (no constrained()) so we can soft-delete
            // the calendar entry without losing the payment trail.
            $table->string('calendar_entry_id', 26)->nullable()->index();
            $table->string('pass_id', 26)->nullable()->index();

            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('PLN');

            $table->string('provider', 32)->index();
            // Provider-side identifier (stripe sess_xxx / payu orderId / etc.)
            // Unique-per-provider in practice; we don't enforce a unique
            // index because the same row can be retried.
            $table->string('provider_ref', 191)->nullable()->index();

            $table->string('status', 32)->index();
            $table->json('provider_data')->nullable();

            // The hosted-checkout URL we emit to the client. Stored so
            // a refresh of /payments/{id}/redirect doesn't generate a
            // new session id with the provider.
            $table->string('checkout_url', 500)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
