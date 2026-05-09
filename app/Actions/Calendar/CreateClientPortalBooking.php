<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use App\Notifications\NewBookingRequestNotification;
use App\Services\Calendar\PublicBookingAvailability;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Self-booking from the client portal — logged-in boarder picks one of
 * their own horses, instructor and slot. Creates a Requested entry that
 * the stable confirms in /app/calendar-entries.
 *
 * Differs from RequestPublicBooking:
 *  - client identity is known (no name/email match-or-create),
 *  - horse_id is required and ownership-checked against the logged client,
 *  - source metadata = "client_portal" so the calendar UI can flag it.
 */
class CreateClientPortalBooking
{
    public function __construct(
        private readonly PublicBookingAvailability $availability,
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @return array{entry: CalendarEntry}
     */
    public function execute(
        Tenant $tenant,
        Client $client,
        string $horseId,
        string $instructorId,
        string $startsAtIso,
        ?string $notes = null,
    ): array {
        $cfg = $this->availability->settingsFor($tenant);
        if (! $cfg['enabled']) {
            throw ValidationException::withMessages([
                'public_booking' => __('portal/booking.errors.disabled'),
            ]);
        }

        // Horse ownership — silent 422, never reveal whether the horse exists.
        $horse = Horse::query()
            ->where('id', $horseId)
            ->where('owner_client_id', $client->id)
            ->first();
        if (! $horse) {
            throw ValidationException::withMessages([
                'horse_id' => __('portal/booking.errors.horse_invalid'),
            ]);
        }

        $instructor = Instructor::query()
            ->where('id', $instructorId)
            ->where('is_active', true)
            ->first();
        if (! $instructor) {
            throw ValidationException::withMessages([
                'instructor_id' => __('portal/booking.errors.instructor_invalid'),
            ]);
        }

        $startsAt = Carbon::parse($startsAtIso);
        $endsAt = $startsAt->copy()->addMinutes($cfg['lesson_duration_minutes']);

        // Re-check the slot against current calendar — defends against
        // double-booking when two clients open the form at the same time.
        $available = $this->availability->slotsFor($tenant, $instructor, $startsAt->copy()->startOfDay())
            ->contains(fn ($slot) => $slot->equalTo($startsAt));
        if (! $available) {
            throw ValidationException::withMessages([
                'starts_at' => __('portal/booking.errors.slot_taken'),
            ]);
        }

        return DB::connection('tenant')->transaction(function () use (
            $tenant, $client, $horse, $instructor, $startsAt, $endsAt, $notes
        ) {
            $entry = CalendarEntry::create([
                'type' => CalendarEntryType::LessonIndividual->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'horse_id' => $horse->id,
                'instructor_id' => $instructor->id,
                'arena_id' => null,
                'client_id' => $client->id,
                'status' => CalendarEntryStatus::Requested->value,
                'notes' => $notes,
                'metadata' => [
                    'source' => 'client_portal',
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);

            $this->audit->record(
                'client_portal.booking_requested',
                'CalendarEntry',
                (string) $entry->getKey(),
                [
                    'horse_id' => (string) $horse->id,
                    'instructor_id' => (string) $instructor->id,
                    'starts_at' => $startsAt->toIso8601String(),
                ],
            );

            $this->notifyStable($tenant, $entry, $client, $horse, $instructor);

            return ['entry' => $entry];
        });
    }

    private function notifyStable(
        Tenant $tenant,
        CalendarEntry $entry,
        Client $client,
        Horse $horse,
        Instructor $instructor,
    ): void {
        $emails = collect((array) (data_get($tenant->settings, 'notifications.booking_request_emails') ?? []))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($emails->isEmpty() && $instructor->email) {
            $emails = collect([$instructor->email]);
        }

        if ($emails->isEmpty()) {
            return;
        }

        Notification::route('mail', $emails->all())->notify(
            new NewBookingRequestNotification(
                tenantName: $tenant->name,
                tenantSlug: (string) $tenant->slug,
                entryId: (string) $entry->id,
                startsAt: $entry->starts_at,
                instructorName: $instructor->name,
                clientName: $client->name,
                clientEmail: (string) ($client->email ?? ''),
                clientPhone: $client->phone,
                notes: $entry->notes !== null ? (string) $entry->notes : null,
            ),
        );
    }
}
