<?php

declare(strict_types=1);

use App\Jobs\Billing\ChargeRecurringPayUSubscriptionsJob;
use App\Jobs\Owner\GenerateMonthlyBoardingInvoicesJob;
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

// PWL document expiry watchdog — codziennie o 04:00 (zaraz po snapshot-health)
// sprawdza wszystkie verified dokumenty transportera w oknie 30 dni przed
// `expires_at` i wysyła mail do owner'a. Per-document expiry_notified_at
// zapewnia idempotencję; re-upload (nowy `updated_at`) re-armuje notify.
Schedule::command('transporter:docs-expiry-notify')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

// KSeF poll — co 30 minut w godzinach roboczych Warsaw aktualizuje
// status submitted invoice'ów do accepted/rejected (asynchroniczne
// callback'i MF). 5min minimum wieku chroni przed natychmiastowym
// pollem (MF potrzebuje czasu na processing), 7 dni max wieku odcina
// utknięte awarie do manualnej obsługi. Patrz docs/TRANSPORT.md §14.1.
Schedule::command('transport:ksef:poll-submitted')
    ->everyThirtyMinutes()
    ->between('06:00', '22:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping(30)
    ->onOneServer();

// Sponsored placements — daily o 02:00 czyści `is_featured=false` dla
// tenantów których `featured_until` już minął. Real-time sort honoruje
// expired w `TransporterRankingService`, ale flagę boolean czyścimy
// raz dziennie żeby admin UI i markFeaturedUntil() rolling extension
// działały spójnie. Patrz docs/TRANSPORT.md §16.
Schedule::command('transport:expire-featured')
    ->dailyAt('02:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping()
    ->onOneServer();

// Auto-billing pensjonatu — 1. dnia każdego miesiąca o 02:00 generuje
// draft invoice dla każdego active HorseBoardingAssignment z items
// (monthly box rate + active monthly boarding services). Operator stajni
// dostaje draft do review przed wystawieniem (KSeF, klient email itp.).
// Idempotent — uniqueId per okres (YYYY-MM), więc retry / podwójny tick
// nie tworzy duplikatów. Patrz docs/OWNER-STABLE-ROADMAP.md Faza 3.
Schedule::job(new GenerateMonthlyBoardingInvoicesJob)
    ->monthlyOn(1, '02:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping()
    ->onOneServer();

// Fuel price snapshot — codziennie o 06:00 Warsaw (po publikacji nowych
// cen przez e-petrol.pl, przed start dnia pracy klienta). Wynik trafia
// do central `fuel_prices` (unique key: type+date+source). Bez tego
// transporter quotes oparte na cenie paliwa lecą po stale starym
// snapshocie. Patrz TransportScrapeFuelCommand docblock.
Schedule::command('transport:scrape-fuel')
    ->dailyAt('06:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping()
    ->onOneServer();

// PayU recurring billing — codziennie 02:00 Warsaw pobiera cykliczne
// opłaty z aktywnych subskrypcji których current_period_end już minął.
// Idempotent: invoice z prefiksem `recur_{sub}_{YYYY-MM}` blokuje
// duplikaty. Dunning (3+7d retry, suspend po 14d) leci przez webhook.
// Patrz docs/BILLING.md (PR 3).
Schedule::job(new ChargeRecurringPayUSubscriptionsJob)
    ->dailyAt('02:00')
    ->timezone('Europe/Warsaw')
    ->withoutOverlapping()
    ->onOneServer();
