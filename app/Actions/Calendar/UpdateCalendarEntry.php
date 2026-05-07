<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Notifications\BookingCancelledClientNotification;
use App\Notifications\BookingConfirmedClientNotification;
use App\Services\Calendar\BookingCancellationLink;
use App\Services\Calendar\ConflictDetector;
use App\Services\Calendar\PassUseManager;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class UpdateCalendarEntry
{
    public function __construct(
        private readonly ConflictDetector $conflicts,
        private readonly TenantAuditLogger $audit,
        private readonly PassUseManager $passes,
        private readonly TenantManager $tenants,
        private readonly BookingCancellationLink $cancelLinks,
    ) {}

    /**
     * @param  array<string,mixed>  $changes  any subset of fillable
     */
    public function execute(CalendarEntry $entry, array $changes): CalendarEntry
    {
        $previousStatus = $entry->status;

        $entry->fill($changes);

        // Public bookings land as `requested` and may not yet have a
        // horse assigned — that's the owner's job when accepting.
        // Block the transition to confirmed/completed if there's still
        // no horse on a lesson.
        $movingOutOfRequested = $previousStatus === CalendarEntryStatus::Requested
            && $entry->status !== CalendarEntryStatus::Requested;
        if ($movingOutOfRequested
            && $entry->status->blocksResources()
            && $entry->type->requiresHorse()
            && empty($entry->horse_id)
        ) {
            throw ValidationException::withMessages([
                'horse_id' => 'Aby potwierdzić rezerwację, wskaż konia.',
            ]);
        }

        // If time / resources changed, re-check conflicts (excluding self).
        if ($entry->isDirty(['starts_at', 'ends_at', 'horse_id', 'instructor_id', 'arena_id', 'status'])) {
            $startsAt = $entry->starts_at instanceof Carbon ? $entry->starts_at : Carbon::parse($entry->starts_at);
            $endsAt = $entry->ends_at instanceof Carbon ? $entry->ends_at : Carbon::parse($entry->ends_at);

            $statusBlocks = $entry->status->blocksResources();

            if ($statusBlocks) {
                $conflicts = $this->conflicts->forProposedEntry(
                    $entry->horse_id,
                    $entry->instructor_id,
                    $entry->arena_id,
                    $startsAt,
                    $endsAt,
                    ignoreEntryId: (string) $entry->getKey(),
                );

                if ($this->conflicts->hasAnyConflict($conflicts)) {
                    throw new CalendarConflictException($conflicts);
                }
            }
        }

        // Snapshot whether a pending pass-use would be restored — captured
        // BEFORE reconcile actually performs the restore, so the customer
        // email reflects what happened.
        $passWouldBeRestored = $entry->status === CalendarEntryStatus::Cancelled
            && $previousStatus->blocksResources()
            && $this->passes->wouldRestoreOnCancel($entry);

        $changedFields = array_keys($entry->getDirty());
        $entry->save();

        if ($changedFields) {
            $this->audit->record(
                'calendar.update',
                'CalendarEntry',
                (string) $entry->getKey(),
                ['changed' => $changedFields],
            );
        }

        $this->reconcilePassUseAfterStatusChange($entry, $previousStatus);
        $this->notifyClientOnStatusTransition($entry, $previousStatus, $passWouldBeRestored);

        return $entry;
    }

    private function reconcilePassUseAfterStatusChange(CalendarEntry $entry, CalendarEntryStatus $previousStatus): void
    {
        if (! $entry->client_id) {
            return;
        }
        if (! in_array($entry->type, [CalendarEntryType::LessonIndividual, CalendarEntryType::LessonGroup], true)) {
            return;
        }

        $newStatus = $entry->status;
        if ($newStatus === $previousStatus) {
            return;
        }

        $wasBlocking = $previousStatus->blocksResources();
        $isBlocking = $newStatus->blocksResources();

        if ($wasBlocking && ! $isBlocking) {
            if ($newStatus === CalendarEntryStatus::Cancelled) {
                $restored = $this->passes->restoreFor($entry, 'cancellation');
                $this->audit->record(
                    $restored ? 'pass.restored' : 'pass.cancellation_late',
                    'CalendarEntry',
                    (string) $entry->getKey(),
                );
            }

            return;
        }

        if (! $wasBlocking && $isBlocking) {
            $use = $this->passes->applyTo($entry);
            if ($use) {
                $this->audit->record(
                    'pass.consumed',
                    'PassUse',
                    (string) $use->getKey(),
                    ['pass_id' => $use->pass_id, 'calendar_entry_id' => $entry->id, 'reactivation' => true],
                );
            }
        }
    }

    /**
     * Customer-facing emails on owner-driven status changes.
     *
     *   requested → confirmed/completed   "Twoja rezerwacja potwierdzona"
     *   * → cancelled                     "Stajnia odwołała Twoją rezerwację"
     *
     * Quietly skips when there's no client email, no active tenant, or
     * the booking didn't change status. `$passWasRestored` is captured
     * by the caller BEFORE reconcile so the email reflects the actual
     * outcome.
     */
    private function notifyClientOnStatusTransition(
        CalendarEntry $entry,
        CalendarEntryStatus $previousStatus,
        bool $passWasRestored,
    ): void {
        if ($entry->status === $previousStatus) {
            return;
        }

        $client = $entry->client;
        if (! $client || ! $client->email) {
            return;
        }

        $tenant = $this->tenants->current();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $becomingConfirmed = $previousStatus === CalendarEntryStatus::Requested
            && in_array($entry->status, [CalendarEntryStatus::Confirmed, CalendarEntryStatus::Completed], true);

        if ($becomingConfirmed) {
            $this->dispatchConfirmedMail($tenant, $entry, $client);

            return;
        }

        $becomingCancelled = $previousStatus->blocksResources()
            && $entry->status === CalendarEntryStatus::Cancelled;

        if ($becomingCancelled) {
            Notification::route('mail', $client->email)->notify(new BookingCancelledClientNotification(
                tenantName: $tenant->name,
                startsAt: $entry->starts_at,
                instructorName: $entry->instructor?->name ?? '—',
                cancelledBy: 'stable',
                passRestored: $passWasRestored,
            ));
        }
    }

    private function dispatchConfirmedMail(Tenant $tenant, CalendarEntry $entry, $client): void
    {
        $duration = (int) $entry->starts_at->diffInMinutes($entry->ends_at);
        $publicProfile = (array) (data_get($tenant->settings, 'public_profile') ?? []);

        Notification::route('mail', $client->email)->notify(new BookingConfirmedClientNotification(
            tenantName: $tenant->name,
            startsAt: $entry->starts_at,
            durationMinutes: $duration,
            instructorName: $entry->instructor?->name ?? '—',
            horseName: $entry->horse?->name,
            arenaName: $entry->arena?->name,
            stableAddress: $publicProfile['address'] ?? null,
            stablePhone: $publicProfile['phone'] ?? null,
            cancelUrl: $this->cancelLinks->for($entry, $tenant->slug),
            cancellationPolicyHours: (int) (data_get($tenant->settings, 'cancellation_policy.hours') ?? 12),
            portalUrl: route('client_portal.login.show', ['slug' => $tenant->slug]),
        ));
    }
}
