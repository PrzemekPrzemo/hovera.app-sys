<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('name', 120);
            $table->unsignedSmallInteger('total_uses');
            // Denormalised for fast queries; recomputed by PassUseManager.
            $table->smallInteger('remaining_uses');

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->unsignedInteger('price_cents')->nullable();

            $table->enum('status', ['active', 'exhausted', 'expired', 'cancelled'])
                ->default('active')
                ->index();

            // Override the tenant-default cancellation window. NULL → fall
            // back to settings.cancellation_policy.hours.
            $table->unsignedSmallInteger('cancellation_policy_hours')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};
