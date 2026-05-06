<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CalendarEntryResource\Pages;

use App\Actions\Calendar\CalendarConflictException;
use App\Actions\Calendar\UpdateCalendarEntry as UpdateAction;
use App\Filament\App\Resources\CalendarEntryResource;
use App\Models\Tenant\CalendarEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCalendarEntry extends EditRecord
{
    protected static string $resource = CalendarEntryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            /** @var CalendarEntry $record */
            return app(UpdateAction::class)->execute($record, $data);
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
    }
}
