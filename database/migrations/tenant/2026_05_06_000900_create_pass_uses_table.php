<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_uses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pass_id')->constrained('passes')->cascadeOnDelete();
            $table->foreignUlid('calendar_entry_id')->constrained('calendar_entries')->cascadeOnDelete();

            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->string('restored_reason', 120)->nullable();

            $table->timestamps();

            // For "did we already consume a pass for this entry?"
            $table->index(['calendar_entry_id', 'restored_at']);
            $table->index(['pass_id', 'restored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_uses');
    }
};
