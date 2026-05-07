<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Notifications\BookingReminderClientNotification;
use App\Services\Calendar\BookingCancellationLink;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Send "your lesson is tomorrow" emails ~24h before a booking starts.
 *
 * Scheduling: runs hourly. Each invocation looks for confirmed entries
 * starting in [now+22h, now+26h) that haven't been reminded yet — the
 * 4h window protects against missed cron ticks (DST, server reboot).
 * `reminder_sent_at` provides idempotency so a duplicate run can't
 * double-send.
 *
 * Per-tenant iteration: skips tenants without an active subscription.
 * If one tenant blows up we log + continue — one bad DB shouldn't kill
 * everyone else's reminders.
 */
class SendBookingRemindersCommand extends Command
{
    protected $signature = 'bookings:send-reminders
        {--tenant= : Slug of a single tenant (default: all active)}
        {--hours-ahead=24 : Centre of the lookup window (h)}
        {--window=2 : Half-width of the window in hours (so total = 2x)}';

    protected $description = 'Email confirmed bookings ~24h before they start.';

    public function handle(
        TenantManager $tenants,
        BookingCancellationLink $cancelLinks,
        TenantAuditLogger $audit,
    ): int {
        $hoursAhead = (int) $this->option('hours-ahead');
        $window = (int) $this->option('window');

        $query = Tenant::query()->whereIn('status', ['trialing', 'active']);
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenantList = $query->get();
        if ($tenantList->isEmpty()) {
            $this->info('No active tenants.');

            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($tenantList as $tenant) {
            try {
                // If we're already in this tenant's context (tests, sequential
                // job runs) skip reconfiguring — TenantManager::setCurrent
                // would otherwise rebuild the connection from MySQL credentials
                // and wipe any test override.
                if ($tenants->current()?->id === $tenant->id) {
                    $sent = $this->processTenant($tenant, $hoursAhead, $window, $cancelLinks, $audit);
                } else {
                    $sent = $tenants->execute(
                        $tenant,
                        fn () => $this->processTenant($tenant, $hoursAhead, $window, $cancelLinks, $audit),
                    );
                }
                $totalSent += $sent;
            } catch (\Throwable $e) {
                $totalSkipped++;
                $this->error("× {$tenant->slug}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Reminders sent: {$totalSent}, tenants skipped: {$totalSkipped}");

        return self::SUCCESS;
    }

    private function processTenant(
        Tenant $tenant,
        int $hoursAhead,
        int $window,
        BookingCancellationLink $cancelLinks,
        TenantAuditLogger $audit,
    ): int {
        $now = Carbon::now();
        $from = $now->copy()->addHours($hoursAhead - $window);
        $to = $now->copy()->addHours($hoursAhead + $window);

        $entries = CalendarEntry::query()
            ->with(['client', 'instructor', 'horse', 'arena'])
            ->where('status', CalendarEntryStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('client_id')
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $sent = 0;
        $publicProfile = (array) (data_get($tenant->settings, 'public_profile') ?? []);
        $cancellationHours = (int) (data_get($tenant->settings, 'cancellation_policy.hours') ?? 12);

        foreach ($entries as $entry) {
            $client = $entry->client;
            if (! $client?->email) {
                $entry->forceFill(['reminder_sent_at' => $now])->save();

                continue;
            }

            $duration = (int) $entry->starts_at->diffInMinutes($entry->ends_at);

            Notification::route('mail', $client->email)->notify(new BookingReminderClientNotification(
                tenantName: $tenant->name,
                startsAt: $entry->starts_at,
                durationMinutes: $duration,
                instructorName: $entry->instructor?->name ?? '—',
                horseName: $entry->horse?->name,
                arenaName: $entry->arena?->name,
                stableAddress: $publicProfile['address'] ?? null,
                stablePhone: $publicProfile['phone'] ?? null,
                cancelUrl: $cancelLinks->for($entry, $tenant->slug),
                cancellationPolicyHours: $cancellationHours,
                portalUrl: route('client_portal.login.show', ['slug' => $tenant->slug]),
            ));

            $entry->forceFill(['reminder_sent_at' => $now])->save();
            $audit->record('booking.reminder_sent', 'CalendarEntry', (string) $entry->getKey());
            $sent++;
        }

        if ($sent > 0) {
            $this->line("→ {$tenant->slug}: {$sent} reminder(s)");
        }

        return $sent;
    }
}
