<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of webhook delivery attempts. Used both for the master
 * admin's relation manager (debugging "why didn't my webhook fire?") and
 * for the future "Resend" action that replays a payload manually.
 *
 * 5xx responses release the job for retry (attempt_number bumps); 4xx are
 * marked failed without retry (caller has to fix their handler/URL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('webhook_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('subscription_id');
            $table->string('event', 100);
            $table->json('payload');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')
                ->references('id')->on('webhook_subscriptions')
                ->cascadeOnDelete();

            $table->index(['subscription_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('webhook_deliveries');
    }
};
