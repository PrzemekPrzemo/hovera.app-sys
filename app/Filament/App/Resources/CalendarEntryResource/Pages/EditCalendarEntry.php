<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CalendarEntryResource\Pages;

use App\Actions\Calendar\CalendarConflictException;
use App\Actions\Calendar\UpdateCalendarEntry as UpdateAction;
use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Filament\App\Resources\CalendarEntryResource;
use App\Models\Tenant\CalendarEntry;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EditCalendarEntry extends EditRecord
{
    protected static string $resource = CalendarEntryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var CalendarEntry $record */
        $previousStatus = $record->status;

        try {
            $updated = app(UpdateAction::class)->execute($record, $data);
        } catch (CalendarConflictException $e) {
            Notification::make()
                ->danger()
                ->title('Konflikt rezerwacji')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.starts_at' => $e->getMessage(),
            ]);
        }

        $this->maybeSuggestFollowupBooking($updated, $previousStatus);

        return $updated;
    }

    private function maybeSuggestFollowupBooking(CalendarEntry $entry, CalendarEntryStatus $previousStatus): void
    {
        $suggestion = self::buildFollowupSuggestion($entry, $previousStatus);
        if ($suggestion === null) {
            return;
        }

        Notification::make()
            ->success()
            ->title(__('app/calendar.followup.title'))
            ->body(__('app/calendar.followup.body', [
                'date' => $suggestion['starts_at']->translatedFormat('d.m.Y H:i'),
            ]))
            ->actions([
                Action::make('plan_next')
                    ->label(__('app/calendar.followup.cta'))
                    ->button()
                    ->url($suggestion['url']),
            ])
            ->send();
    }

    /**
     * Public dla testów — decyzja "czy proponować kolejną lekcję" + budowa
     * URL do create form'a z prefilled query string. Zwraca `null` gdy
     * sugestia nie ma sensu (status nie zmienił się na completed, typ nie
     * jest cykliczny, itp.).
     *
     * Reguły:
     *  - transition `* → completed` (poza completed → completed)
     *  - tylko lesson_individual / lesson_group / training (cykliczne typy)
     *  - data +7 dni od bieżącego starts_at
     *
     * @return array{url:string,starts_at:Carbon,ends_at:Carbon}|null
     */
    public static function buildFollowupSuggestion(CalendarEntry $entry, CalendarEntryStatus $previousStatus): ?array
    {
        if ($previousStatus === CalendarEntryStatus::Completed) {
            return null;
        }
        if ($entry->status !== CalendarEntryStatus::Completed) {
            return null;
        }
        if (! in_array($entry->type, [
            CalendarEntryType::LessonIndividual,
            CalendarEntryType::LessonGroup,
            CalendarEntryType::Training,
        ], true)) {
            return null;
        }

        $startsAt = $entry->starts_at->copy()->addDays(7);
        $endsAt = $entry->ends_at->copy()->addDays(7);

        $url = CalendarEntryResource::getUrl('create', array_filter([
            'type' => $entry->type->value,
            'horse_id' => $entry->horse_id,
            'instructor_id' => $entry->instructor_id,
            'arena_id' => $entry->arena_id,
            'client_id' => $entry->client_id,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
        ]));

        return [
            'url' => $url,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }
}
