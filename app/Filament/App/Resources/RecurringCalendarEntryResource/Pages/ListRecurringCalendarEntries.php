<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\RecurringCalendarEntryResource\Pages;

use App\Filament\App\Resources\RecurringCalendarEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecurringCalendarEntries extends ListRecords
{
    protected static string $resource = RecurringCalendarEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
