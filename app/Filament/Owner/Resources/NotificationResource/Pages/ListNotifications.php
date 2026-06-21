<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\NotificationResource\Pages;

use App\Filament\Owner\Resources\NotificationResource;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;
}
