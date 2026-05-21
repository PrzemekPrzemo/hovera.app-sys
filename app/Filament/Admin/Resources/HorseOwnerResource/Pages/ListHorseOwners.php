<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\HorseOwnerResource\Pages;

use App\Filament\Admin\Resources\HorseOwnerResource;
use Filament\Resources\Pages\ListRecords;

class ListHorseOwners extends ListRecords
{
    protected static string $resource = HorseOwnerResource::class;
}
