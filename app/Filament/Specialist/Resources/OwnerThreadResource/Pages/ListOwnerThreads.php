<?php

declare(strict_types=1);

namespace App\Filament\Specialist\Resources\OwnerThreadResource\Pages;

use App\Filament\Specialist\Resources\OwnerThreadResource;
use Filament\Resources\Pages\ListRecords;

class ListOwnerThreads extends ListRecords
{
    protected static string $resource = OwnerThreadResource::class;
}
