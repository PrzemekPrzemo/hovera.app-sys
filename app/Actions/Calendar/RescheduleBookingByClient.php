<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Services\Calendar\ConflictDetector;
use App\Services\Calendar\PublicBookingAvailability;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Client-driven reschedule. Differs from a stable-driven UpdateCalendarEntry:
 *
 *   - Source is the public portal — caller is the client themselves
 *   - Status stays Confirmed; only starts_at / ends_at change
 *   - Caps the number of self-service reschedules per booking via
 *     metadata.client_reschedule_count (default 2). After that the
 *     client must contact the stable.
 *   - Honours the cancellation policy as a "reschedule lead time" —
 *     too late to cancel = too late to reschedule
 *   - Requires the new slot to be inside the instructor's availability
 *     (via PublicBookingAvailability — same definition the public
 *     booking flow uses, no special back-door)
 */
class RescheduleBookingByClient
{
    public const MAX_RESCHEDULES_DEFAULT = 2;

    public function __construct(
        private readonly PublicBookingAvailability $availability,
        private readonly ConflictDetector $conflicts,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(
        Tenant $tenant,
        CalendarEntry $entry,
        Client $client,
        Carbon $newStartsAt,
    ): CalendarEntry {
        if ($entry->client_id !== $client->id) {
            throw ValidationException::withMessages(['entry' => 'Brak uprawnień do tej rezerwacji.']);
        }

        if ($entry->status !== CalendarEntryStatus::Confirmed) {
            throw ValidationException::withMessages(['entry' => 'Można przesunąć tylko potwierdzoną rezerwację.']);
        }

        $cancellationHours = (int) (data_get($tenant->settings, 'cancellation_policy.hours') ?? 12);
        $threshold = $entry->starts_at->copy()->subHours($cancellationHours);
        if (now()->gt($threshold)) {
            throw ValidationException::withMessages([
                'entry' => "Termin minął. Na przesunięcie potrzeba minimum {$cancellationHours} godzin przed lekcją.",
            ]);
        }

        $maxReschedules = (int) (data_get($tenant->settings, 'public_booking.max_client_reschedules')
            ?? self::MAX_RESCHEDULES_DEFAULT);
        $count = (int) (data_get($entry->metadata, 'client_reschedule_count') ?? 0);
        if ($count >= $maxReschedules) {
            throw ValidationException::withMessages([
                'entry' => 'Osiągnięto limit samodzielnych przesunięć — skontaktuj się ze stajnią.',
            ]);
        }

        $instructor = $entry->instructor;
        if (! $instructor || ! $instructor->is_active) {
            throw ValidationException::withMessages(['entry' => 'Instruktor jest niedostępny.']);
        }

        $duration = (int) $entry->starts_at->diffInMinutes($entry->ends_at);
        $newEndsAt = $newStartsAt->copy()->addMinutes($duration);

        // Slot must be in the official availability set so we can't
        // bypass working hours / advance windows by typing a custom time.
        $available = $this->availability->slotsFor($tenant, $instructor, $newStartsAt->copy()->startOfDay());
        $matches = $available->contains(
            fn (Carbon $slot) => $slot->equalTo($newStartsAt),
        );
        if (! $matches) {
            throw ValidationException::withMessages(['starts_at' => 'Wybrany termin jest niedostępny.']);
        }

        // Defence in depth — slotsFor already filters instructor conflicts,
        // but check horse + arena too if assigned.
        $hasConflict = $this->conflicts->forProposedEntry(
            horseId: $entry->horse_id,
            instructorId: $entry->instructor_id,
            arenaId: $entry->arena_id,
            startsAt: $newStartsAt,
            endsAt: $newEndsAt,
            ignoreEntryId: (string) $entry->getKey(),
        );
        if ($this->conflicts->hasAnyConflict($hasConflict)) {
            throw ValidationException::withMessages(['starts_at' => 'Wybrany termin koliduje z innymi zajęciami.']);
        }

        $metadata = $entry->metadata ?? [];
        $metadata['client_reschedule_count'] = $count + 1;
        $metadata['client_reschedule_history'][] = [
            'at' => now()->toIso8601String(),
            'from' => $entry->starts_at->toIso8601String(),
            'to' => $newStartsAt->toIso8601String(),
        ];

        $entry->forceFill([
            'starts_at' => $newStartsAt,
            'ends_at' => $newEndsAt,
            'metadata' => $metadata,
        ])->save();

        return $entry->refresh();
    }
}
