<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency markers dla cyklicznych przypomnień o ekspiracji health
 * record (np. szczepienia). Daily command `health-records:remind-due`
 * iteruje rekordy gdzie `next_due_at` zbliża się w oknach 30/14/7 dni
 * i wysyła powiadomienia + tworzy CalendarEntry; te kolumny zapobiegają
 * podwójnemu firingowi.
 *
 * Reset: gdy `next_due_at` zostanie zaktualizowany (np. po wizycie weta
 * i zapisaniu kolejnego terminu), command sprawdza `updated_at > sent_at`
 * i re-armuje cykl.
 *
 * `reminder_calendar_entry_id` trzyma ULID utworzonego CalendarEntry —
 * gdy klient go usunie albo `next_due_at` się zmieni, command odtworzy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_records', function (Blueprint $table): void {
            $table->timestamp('reminder_30d_sent_at')->nullable()->after('next_due_at');
            $table->timestamp('reminder_14d_sent_at')->nullable()->after('reminder_30d_sent_at');
            $table->timestamp('reminder_7d_sent_at')->nullable()->after('reminder_14d_sent_at');
            $table->string('reminder_calendar_entry_id', 26)->nullable()->after('reminder_7d_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table): void {
            $table->dropColumn([
                'reminder_30d_sent_at',
                'reminder_14d_sent_at',
                'reminder_7d_sent_at',
                'reminder_calendar_entry_id',
            ]);
        });
    }
};
