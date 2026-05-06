<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_calendar_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 160);
            $table->enum('type', [
                'lesson_individual', 'lesson_group',
                'training', 'care',
            ])->index();

            $table->time('starts_time');                // 17:00:00
            $table->unsignedSmallInteger('duration_minutes');

            // Default resources copied onto each occurrence
            $table->foreignUlid('horse_id')->nullable()->constrained('horses')->nullOnDelete();
            $table->foreignUlid('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->foreignUlid('arena_id')->nullable()->constrained('arenas')->nullOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // Recurrence rule
            $table->enum('recurrence_pattern', ['daily', 'weekly', 'monthly']);
            $table->unsignedTinyInteger('recurrence_interval')->default(1);
            // Weekly: array of 0-6 (0 = Sunday). Stored on all patterns
            // for normalisation but only meaningful for weekly.
            $table->json('recurrence_days_of_week')->nullable();

            $table->date('recurrence_starts_on');
            $table->date('recurrence_ends_on')->nullable();   // NULL = open-ended
            $table->unsignedSmallInteger('max_occurrences')->nullable();

            $table->string('title', 160)->nullable();         // copied to entries (e.g. group lesson name)
            $table->text('notes')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->json('metadata')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by_central_user_id', 26)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_calendar_entries');
    }
};
