<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\HorseResource\Pages;

use App\Filament\Owner\Resources\HorseResource;
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
