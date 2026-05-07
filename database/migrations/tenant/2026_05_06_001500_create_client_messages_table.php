<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant log of every customer-facing email we dispatched —
 * powers the "Wiadomości" tab in the client portal so riders can see
 * all communications in one place rather than digging through inbox.
 *
 * Type strings are namespaced like "booking.confirmed" / "portal.magic_link"
 * so we can grow the catalogue without schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();

            // Free-form, dot-namespaced. Examples:
            //   booking.requested  booking.confirmed  booking.cancelled
            //   booking.reminder   booking.rescheduled
            //   portal.magic_link  invoice.issued
            $table->string('type', 64)->index();

            $table->string('subject', 255);
            $table->string('to_email', 255);
            $table->json('preview')->nullable();   // small payload, not full body
            $table->string('related_type', 60)->nullable();
            $table->string('related_id', 26)->nullable();

            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            $table->index(['client_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_messages');
    }
};
