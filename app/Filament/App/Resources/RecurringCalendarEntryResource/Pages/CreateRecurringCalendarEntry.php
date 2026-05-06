<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\RecurringCalendarEntryResource\Pages;

use App\Actions\Calendar\CreateRecurringSeries;
use App\Filament\App\Resources\RecurringCalendarEntryResource;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Services\TenantAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringCalendarEntry extends CreateRecord
{
    protected static string $resource = RecurringCalendarEntryResource::class;

    protected function afterCreate(): void
    {
        /** @var RecurringCalendarEntry $series */
        $series = $this->record;

        app(TenantAuditLogger::class)->record(
            'recurrence.create',
            'RecurringCalendarEntry',
            (string) $series->getKey(),
            ['name' => $series->name, 'pattern' => $series->recurrence_pattern->value],
        );

        // Auto-expand on create — much friendlier than asking the user
        // to click "Wygeneruj wystąpienia" right after saving the form.
        $result = app(CreateRecurringSeries::class)->execute($series);

        $body = "Wygenerowano {$result['created']} wystąpień.";
        if (count($result['skipped_conflicts']) > 0) {
            $body .= ' Pominięto: '.implode(', ', array_slice($result['skipped_conflicts'], 0, 5)).'.';
        }

        Notification::make()
            ->success()
            ->title('Seria utworzona i rozwinięta')
            ->body($body)
            ->persistent()
            ->send();
    }
}
