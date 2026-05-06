<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\PassResource\Pages;

use App\Filament\App\Resources\PassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPasses extends ListRecords
{
    protected static string $resource = PassResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
