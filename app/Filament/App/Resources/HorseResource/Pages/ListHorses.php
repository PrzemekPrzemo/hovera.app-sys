<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\Pages;

use App\Filament\App\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHorses extends ListRecords
{
    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
