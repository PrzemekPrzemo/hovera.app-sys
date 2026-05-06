<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();

            $table->enum('type', [
                'vaccination', 'deworming', 'vet_visit',
                'farrier', 'dentist', 'check_up',
                'medication', 'other',
            ])->index();

            $table->timestamp('performed_at')->index();
            $table->string('performed_by', 255)->nullable();

            $table->string('summary', 255);
            $table->text('details')->nullable();

            // Drives the alerts dashboard. NULL = no follow-up due
            // (e.g. one-off check-up).
            $table->date('next_due_at')->nullable()->index();

            $table->unsignedInteger('cost_cents')->nullable();

            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            $table->string('created_by_central_user_id', 26)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Timeline per koń + alert query per koń
            $table->index(['horse_id', 'performed_at']);
            $table->index(['horse_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
