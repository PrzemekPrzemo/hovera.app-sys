<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Booking reminders go out hourly. The command uses a 4h lookup window
// plus per-row reminder_sent_at to stay idempotent across DST, missed
// cron ticks and queue retries.
Schedule::command('bookings:send-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Daily health snapshot — recomputes health_score + last_activity_at
// from each tenant DB so the master dashboard reflects yesterday's
// reality without per-request tenant DB reads.
Schedule::command('tenants:snapshot-health')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();
