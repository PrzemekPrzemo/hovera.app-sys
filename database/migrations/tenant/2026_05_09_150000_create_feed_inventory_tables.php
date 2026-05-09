<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 120)->index();
            $table->string('unit', 20)->default('kg');

            // Below this current stock the item shows a low-stock alert.
            // NULL = no alert configured.
            $table->decimal('low_stock_threshold', 10, 2)->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });

        Schema::create('feed_stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('feed_item_id')->constrained('feed_items')->cascadeOnDelete();

            // Signed delta — positive for purchase / adjustment-up,
            // negative for consumption / waste / adjustment-down. The
            // current stock is SUM(delta) per item.
            $table->decimal('delta', 10, 2);

            $table->enum('kind', ['purchase', 'consumption', 'adjustment', 'waste'])->index();
            $table->date('movement_date')->index();

            $table->text('notes')->nullable();
            $table->string('user_central_id', 26)->nullable();

            $table->timestamps();

            $table->index(['feed_item_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_stock_movements');
        Schema::dropIfExists('feed_items');
    }
};
