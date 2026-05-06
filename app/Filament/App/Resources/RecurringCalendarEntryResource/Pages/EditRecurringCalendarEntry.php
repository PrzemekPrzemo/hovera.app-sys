<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\RecurringCalendarEntryResource\Pages;

use App\Filament\App\Resources\RecurringCalendarEntryResource;
use Filament\Resources\Pages\EditRecord;

class EditRecurringCalendarEntry extends EditRecord
{
    protected static string $resource = RecurringCalendarEntryResource::class;
}
