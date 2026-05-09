<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_entry_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('calendar_entry_id')
                ->constrained('calendar_entries')
                ->cascadeOnDelete();

            // Both nullable — group lessons can have a guest rider without a
            // saved client record, or a stable horse without a known rider.
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUlid('horse_id')->nullable()->constrained('horses')->nullOnDelete();

            // Per-participant attendance — used by the trainer after the
            // lesson to mark each rider individually.
            $table->enum('attendance_status', ['expected', 'present', 'absent', 'late'])
                ->default('expected');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['calendar_entry_id', 'client_id']);
            $table->index(['calendar_entry_id', 'horse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_entry_participants');
    }
};
