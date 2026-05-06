<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Type drives required vs optional fields:
            //   lesson_individual  horse + instructor + arena + client
            //   lesson_group       instructor + arena (clients via separate
            //                       table in a future iteration)
            //   training           horse + instructor (+ arena optional)
            //   care               horse (+ external party in notes)
            //   event              free-form (zawody, wyjazd)
            //   block              resource block (no horse/instructor —
            //                       e.g. arena maintenance)
            $table->enum('type', [
                'lesson_individual', 'lesson_group',
                'training', 'care', 'event', 'block',
            ])->index();

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();

            $table->foreignUlid('horse_id')->nullable()->constrained('horses')->nullOnDelete();
            $table->foreignUlid('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->foreignUlid('arena_id')->nullable()->constrained('arenas')->nullOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->enum('status', [
                'requested', 'confirmed', 'cancelled', 'completed', 'no_show',
            ])->default('confirmed')->index();

            $table->string('title', 160)->nullable();   // for events / blocks
            $table->text('notes')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->json('metadata')->nullable();

            $table->string('created_by_central_user_id', 26)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Conflict-detection / range queries hit (resource_id, time)
            // pairs heavily — composite indexes pay off quickly.
            $table->index(['horse_id', 'starts_at', 'ends_at']);
            $table->index(['instructor_id', 'starts_at', 'ends_at']);
            $table->index(['arena_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_entries');
    }
};
