<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->foreignUlid('recurrence_id')->nullable()->after('client_id')
                ->constrained('recurring_calendar_entries')->nullOnDelete();
            $table->unsignedSmallInteger('recurrence_occurrence')->nullable()->after('recurrence_id');

            $table->index(['recurrence_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->dropForeign(['recurrence_id']);
            $table->dropIndex(['recurrence_id', 'starts_at']);
            $table->dropColumn(['recurrence_id', 'recurrence_occurrence']);
        });
    }
};
