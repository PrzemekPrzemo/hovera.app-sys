<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Models\Tenant\CalendarEntry;
use App\Services\Calendar\ConflictDetector;
use App\Services\Calendar\PassUseManager;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateCalendarEntry
{
    public function __construct(
        private readonly ConflictDetector $conflicts,
        private readonly TenantAuditLogger $audit,
        private readonly PassUseManager $passes,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     */
    public function execute(array $input): CalendarEntry
    {
        $data = $this->validate($input);
        $type = CalendarEntryType::from($data['type']);

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $status = CalendarEntryStatus::from($data['status'] ?? CalendarEntryStatus::Confirmed->value);

        $this->validateRequiredResources($type, $data, $status);
        $this->validateConflicts(
            $data['horse_id'] ?? null,
            $data['instructor_id'] ?? null,
            $data['arena_id'] ?? null,
            $startsAt,
            $endsAt,
        );

        $entry = CalendarEntry::create([
            'type' => $type->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'horse_id' => $data['horse_id'] ?? null,
            'instructor_id' => $data['instructor_id'] ?? null,
            'arena_id' => $data['arena_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? CalendarEntryStatus::Confirmed->value,
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'] ?? null,
            'price_cents' => $data['price_cents'] ?? null,
            'created_by_central_user_id' => Auth::id(),
        ]);

        $this->audit->record(
            'calendar.create',
            'CalendarEntry',
            (string) $entry->getKey(),
            [
                'type' => $type->value,
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => $entry->durationMinutes(),
            ],
        );

        // Auto-consume a pass if the booking is a lesson with a known
        // client and they have any usable pass. Silent no-op otherwise
        // (booking still proceeds — they'll pay another way).
        if ($entry->status->blocksResources()
            && in_array($type, [CalendarEntryType::LessonIndividual, CalendarEntryType::LessonGroup], true)
            && $entry->client_id
        ) {
            $use = $this->passes->applyTo($entry);
            if ($use) {
                $this->audit->record(
                    'pass.consumed',
                    'PassUse',
                    (string) $use->getKey(),
                    ['pass_id' => $use->pass_id, 'calendar_entry_id' => $entry->id],
                );
            }
        }

        return $entry;
    }

    private function validate(array $input): array
    {
        $validator = validator($input, [
            'type' => ['required', Rule::in(array_column(CalendarEntryType::cases(), 'value'))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'horse_id' => ['sometimes', 'nullable', 'string'],
            'instructor_id' => ['sometimes', 'nullable', 'string'],
            'arena_id' => ['sometimes', 'nullable', 'string'],
            'client_id' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(array_column(CalendarEntryStatus::cases(), 'value'))],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateRequiredResources(CalendarEntryType $type, array $data, CalendarEntryStatus $status): void
    {
        $errors = [];

        // `requested` is a "pending approval" state — public booking
        // creates entries here without a horse, the stable owner picks
        // the horse when confirming. Horse is enforced as soon as the
        // status moves to confirmed/completed (handled by UpdateCalendarEntry).
        $horseRequired = $type->requiresHorse() && $status !== CalendarEntryStatus::Requested;

        if ($horseRequired && empty($data['horse_id'])) {
            $errors['horse_id'] = "Typ {$type->label()} wymaga wskazania konia.";
        }
        if ($type->requiresInstructor() && empty($data['instructor_id'])) {
            $errors['instructor_id'] = "Typ {$type->label()} wymaga wskazania instruktora.";
        }
        if ($type === CalendarEntryType::Block && empty($data['arena_id']) && empty($data['horse_id'])) {
            $errors['arena_id'] = 'Blokada musi dotyczyć konkretnego zasobu (ujeżdżalnia lub koń).';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateConflicts(
        ?string $horseId,
        ?string $instructorId,
        ?string $arenaId,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
    ): void {
        $conflicts = $this->conflicts->forProposedEntry(
            $horseId, $instructorId, $arenaId,
            $startsAt, $endsAt,
        );

        if ($this->conflicts->hasAnyConflict($conflicts)) {
            throw new CalendarConflictException($conflicts);
        }
    }
}
