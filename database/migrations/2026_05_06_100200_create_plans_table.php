<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 32)->unique();           // free, solo, stable, pro, enterprise
            $table->string('name');
            $table->string('currency', 3)->default('PLN');
            $table->unsignedInteger('price_monthly_cents')->default(0);
            $table->unsignedInteger('price_yearly_cents')->default(0);
            $table->json('limits')->nullable();             // {max_horses, max_seats, ...}
            $table->json('features')->nullable();           // {ksef, ai_copilot, ...}
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);    // shown on pricing page
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
