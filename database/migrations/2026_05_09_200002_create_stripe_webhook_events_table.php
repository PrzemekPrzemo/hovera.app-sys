<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency log for incoming Stripe webhooks. Stripe retries delivery
 * with the same `event.id` for ~3 days on 5xx — we MUST dedupe by id
 * so that e.g. `customer.subscription.deleted` doesn't double-cancel.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->create('stripe_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('event_id')->unique();
            $table->string('type')->index();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('stripe_webhook_events');
    }
};
