<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Resources\ThreadResource\Pages;

use App\Filament\Specialist\Resources\ThreadResource;
use Filament\Resources\Pages\ListRecords;

class ListThreads extends ListRecords
{
    protected static string $resource = ThreadResource::class;
}
