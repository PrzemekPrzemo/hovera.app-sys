<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_addons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('code', 64);                     // e.g. horses_plus_10
            $table->string('name');                         // e.g. "+10 koni"
            $table->text('description')->nullable();
            $table->string('resource_type', 32)->nullable(); // horses, users, clients, storage_gb, custom
            $table->integer('quantity')->nullable();        // 10 (positive int — additive)
            $table->unsignedInteger('price_monthly_cents')->default(0);
            $table->unsignedInteger('price_yearly_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_addons');
    }
};
