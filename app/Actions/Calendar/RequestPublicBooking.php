<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Instructor;
use App\Notifications\BookingRequestedClientNotification;
use App\Notifications\NewBookingRequestNotification;
use App\Services\Calendar\BookingCancellationLink;
use App\Services\Calendar\ConflictDetector;
use App\Services\Calendar\PublicBookingAvailability;
use App\Services\Portal\ClientMessageJournal;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public-flow booking request:
 *
 *   1. Validate input (type, instructor, time, contact)
 *   2. Re-check the slot is still free (race-safe)
 *   3. Match an existing Client by email or create a new one (light
 *      record — owner can dedupe / enrich later)
 *   4. Create a CalendarEntry with status=requested, no horse_id
 *      (owner picks a horse during confirmation)
 *   5. Notify all stable owners/admins by email
 *   6. Audit on the per-tenant log
 *
 * The booking is NOT auto-confirmed — staff approval is part of the
 * design. We don't want a stranger pulling Bucefał off the schedule
 * accidentally.
 */
class RequestPublicBooking
{
    public function __construct(
        private readonly PublicBookingAvailability $availability,
        private readonly ConflictDetector $conflicts,
        private readonly TenantAuditLogger $audit,
        private readonly ClientMessageJournal $journal,
    ) {}

    /**
     * @param  array{
     *     instructor_id:string, starts_at:string,
     *     name:string, email:string, phone?:string|null, notes?:string|null,
     * }  $input
     * @return array{client:Client, entry:CalendarEntry}
     */
    public function execute(Tenant $tenant, array $input): array
    {
        $cfg = $this->availability->settingsFor($tenant);
        if (! $cfg['enabled']) {
            throw ValidationException::withMessages([
                'public_booking' => 'Online booking jest wyłączony dla tej stajni.',
            ]);
        }

        $data = $this->validate($input);

        $instructor = Instructor::query()
            ->where('id', $data['instructor_id'])
            ->where('is_active', true)
            ->first();

        if (! $instructor) {
            throw ValidationException::withMessages([
                'instructor_id' => 'Instruktor jest niedostępny.',
            ]);
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($cfg['lesson_duration_minutes']);

        $this->ensureSlotStillFree($instructor, $startsAt, $endsAt, $cfg);

        return DB::connection('tenant')->transaction(function () use ($tenant, $instructor, $data, $startsAt, $endsAt) {
            $client = $this->matchOrCreateClient($data);

            $entry = CalendarEntry::create([
                'type' => CalendarEntryType::LessonIndividual->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'horse_id' => null,                       // owner assigns
                'instructor_id' => $instructor->id,
                'arena_id' => null,
                'client_id' => $client->id,
                'status' => CalendarEntryStatus::Requested->value,
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'source' => 'public_booking',
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);

            $this->audit->record(
                'public_booking.requested',
                'CalendarEntry',
                (string) $entry->getKey(),
                [
                    'client_email' => $client->email,
                    'instructor_id' => $instructor->id,
                    'starts_at' => $startsAt->toIso8601String(),
                ],
            );

            $this->notifyOwners($tenant, $entry, $client, $instructor);
            $this->notifyClient($tenant, $entry, $client, $instructor);

            return [
                'client' => $client,
                'entry' => $entry,
            ];
        });
    }

    private function validate(array $input): array
    {
        $validator = validator($input, [
            'instructor_id' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function ensureSlotStillFree(Instructor $instructor, Carbon $startsAt, Carbon $endsAt, array $cfg): void
    {
        // Anti-last-minute / anti-far-future
        $earliest = now()->addHours($cfg['advance_min_hours']);
        $latest = now()->addDays($cfg['advance_max_days']);
        if ($startsAt->lt($earliest) || $startsAt->gt($latest)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Wybrany termin jest poza dostępnym oknem rezerwacji.',
            ]);
        }

        $busy = $this->conflicts
            ->forInstructor((string) $instructor->id, $startsAt, $endsAt)
            ->isNotEmpty();

        if ($busy) {
            throw ValidationException::withMessages([
                'starts_at' => 'Ten termin został zajęty zanim ukończyłeś rezerwację. Wybierz inny.',
            ]);
        }
    }

    private function matchOrCreateClient(array $data): Client
    {
        $email = Str::lower($data['email']);

        $existing = Client::query()->where('email', $email)->first();
        if ($existing) {
            // Touch contact info if it's missing — don't overwrite.
            $existing->forceFill(array_filter([
                'phone' => $existing->phone ?: ($data['phone'] ?? null),
                'name' => $existing->name ?: $data['name'],
            ]))->save();

            return $existing;
        }

        return Client::create([
            'type' => 'individual',
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'metadata' => ['source' => 'public_booking'],
        ]);
    }

    private function notifyOwners(Tenant $tenant, CalendarEntry $entry, Client $client, Instructor $instructor): void
    {
        $ownerEmails = User::query()
            ->whereIn('id', TenantMembership::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('revoked_at')
                ->whereIn('role', ['owner', 'admin'])
                ->pluck('user_id'))
            ->pluck('email')
            ->all();

        if ($ownerEmails === []) {
            return;
        }

        Notification::route('mail', $ownerEmails)->notify(new NewBookingRequestNotification(
            tenantName: $tenant->name,
            tenantSlug: $tenant->slug,
            entryId: $entry->id,
            startsAt: $entry->starts_at,
            instructorName: $instructor->name,
            clientName: $client->name,
            clientEmail: $client->email,
            clientPhone: $client->phone,
            notes: $entry->notes,
        ));
    }

    private function notifyClient(Tenant $tenant, CalendarEntry $entry, Client $client, Instructor $instructor): void
    {
        if (! $client->email) {
            return;
        }

        $duration = (int) $entry->starts_at->diffInMinutes($entry->ends_at);
        $cancelUrl = app(BookingCancellationLink::class)
            ->for($entry, $tenant->slug);

        Notification::route('mail', $client->email)->notify(new BookingRequestedClientNotification(
            tenantName: $tenant->name,
            startsAt: $entry->starts_at,
            durationMinutes: $duration,
            instructorName: $instructor->name,
            cancelUrl: $cancelUrl,
            cancellationPolicyHours: (int) (data_get($tenant->settings, 'cancellation_policy.hours') ?? 12),
            portalUrl: route('client_portal.login.show', ['slug' => $tenant->slug]),
        ));
        $this->journal->record(
            $client,
            'booking.requested',
            "Otrzymaliśmy zgłoszenie — {$tenant->name}",
            ['starts_at' => $entry->starts_at->toIso8601String(), 'duration' => $duration],
            'CalendarEntry',
            (string) $entry->id,
        );
    }
}
