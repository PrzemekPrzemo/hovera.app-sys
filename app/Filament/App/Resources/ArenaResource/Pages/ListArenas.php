<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ArenaResource\Pages;

use App\Filament\App\Resources\ArenaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArenas extends ListRecords
{
    protected static string $resource = ArenaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
