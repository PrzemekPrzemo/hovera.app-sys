<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\PoiResource\Pages;

use App\Filament\Transport\Resources\PoiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPois extends ListRecords
{
    protected static string $resource = PoiResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
