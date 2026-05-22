<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\MyRoutesResource\Pages;

use App\Filament\Transport\Resources\MyRoutesResource;
use Filament\Resources\Pages\ListRecords;

class ListMyRoutes extends ListRecords
{
    protected static string $resource = MyRoutesResource::class;
}
