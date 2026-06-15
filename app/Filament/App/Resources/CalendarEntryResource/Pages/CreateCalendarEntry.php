<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CalendarEntryResource\Pages;

use App\Actions\Calendar\CalendarConflictException;
use App\Actions\Calendar\CreateCalendarEntry as CreateAction;
use App\Filament\App\Resources\CalendarEntryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Routes Filament's default create through CreateCalendarEntry so we
 * get the conflict-detection / required-resources validation, and
 * surface a clean error toast instead of an exception page when a slot
 * is already taken.
 */
class CreateCalendarEntry extends CreateRecord
{
    protected static string $resource = CalendarEntryResource::class;

    /**
     * Prefill z query string — używane przez follow-up toast po complete
     * lekcji (`EditCalendarEntry::maybeSuggestFollowupBooking`). Akceptowane
     * klucze: type, horse_id, instructor_id, arena_id, client_id, starts_at,
     * ends_at. Pozostałe query params ignorowane.
     */
    protected function fillForm(): void
    {
        $allowed = ['type', 'horse_id', 'instructor_id', 'arena_id', 'client_id', 'starts_at', 'ends_at'];
        $prefill = array_filter(
            request()->only($allowed),
            static fn ($v) => $v !== null && $v !== '',
        );

        if ($prefill === []) {
            parent::fillForm();

            return;
        }

        $this->form->fill($prefill);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateAction::class)->execute($data);
        } catch (CalendarConflictException $e) {
            Notification::make()
                ->danger()
                ->title('Konflikt rezerwacji')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            // Pass back as a form validation failure so the user stays
            // on the form with their data preserved.
            throw ValidationException::withMessages([
                'data.starts_at' => $e->getMessage(),
            ]);
        }
    }
}
