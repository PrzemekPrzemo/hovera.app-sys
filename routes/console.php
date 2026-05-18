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

// Public demo tenant reset — wipes all user-edited data and reseeds the
// baseline at 22:00 every day. Visitors via `/demo` always land on a
// fresh, predictable dataset the next morning.
Schedule::command('hovera:demo:reset')
    ->dailyAt('22:00')
    ->withoutOverlapping()
    ->onOneServer();

// Transport reviews — magic-link invite 14 dni po preferred_date dla
// zaakceptowanych ofert. Idempotent (unique key chroni przed double-send).
// 09:00 Warsaw — rano, w godzinach roboczych zamawiającego. Patrz
// docs/TRANSPORT.md §12.
Schedule::command('transport:dispatch-review-invites')
    ->dailyAt('09:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping()
    ->onOneServer();
