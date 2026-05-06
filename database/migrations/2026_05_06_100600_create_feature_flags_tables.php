<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('description')->nullable();
            $table->boolean('default_enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percent')->default(0);
            $table->boolean('killed')->default(false);   // global kill-switch
            $table->timestamps();
        });

        Schema::create('feature_flag_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('flag_id')->constrained('feature_flags')->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['flag_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
        Schema::dropIfExists('feature_flags');
    }
};
