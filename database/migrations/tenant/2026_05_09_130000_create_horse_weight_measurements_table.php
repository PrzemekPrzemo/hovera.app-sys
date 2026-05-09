<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_weight_measurements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();

            $table->date('measured_at')->index();
            $table->decimal('weight_kg', 5, 1);

            // Optional girth circumference in cm — useful proxy when no
            // scale is available (formula: girth^2 × length / X). NULL = not measured.
            $table->decimal('girth_cm', 5, 1)->nullable();

            $table->text('notes')->nullable();

            $table->string('measured_by_central_user_id', 26)->nullable();

            $table->timestamps();

            // Trend query per horse (most-recent N) hits this index.
            $table->index(['horse_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_weight_measurements');
    }
};
