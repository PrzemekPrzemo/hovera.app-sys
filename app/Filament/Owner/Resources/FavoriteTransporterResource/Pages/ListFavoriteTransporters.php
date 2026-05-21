<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\FavoriteTransporterResource\Pages;

use App\Filament\Owner\Resources\FavoriteTransporterResource;
use Filament\Resources\Pages\ListRecords;

class ListFavoriteTransporters extends ListRecords
{
    protected static string $resource = FavoriteTransporterResource::class;
}
