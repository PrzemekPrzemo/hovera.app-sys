<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_feeding_plan_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();

            $table->enum('meal', ['breakfast', 'midday', 'evening', 'night'])->index();

            // Free-text feed name keeps schema flexible — link to FeedItem
            // (when stock module ships) is opt-in via metadata or a future column.
            $table->string('feed_type', 120);
            $table->decimal('amount_kg', 5, 2);
            $table->string('unit', 20)->default('kg');

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['horse_id', 'meal', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_feeding_plan_items');
    }
};
