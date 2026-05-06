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
            // Set when the 24h reminder email is dispatched. Used to keep
            // the daily scheduler idempotent — if the cron fires twice or
            // the worker retries, we won't double-send.
            $table->timestamp('reminder_sent_at')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
