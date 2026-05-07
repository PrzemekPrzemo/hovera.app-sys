<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\BoxResource\Pages;

use App\Filament\App\Resources\BoxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoxes extends ListRecords
{
    protected static string $resource = BoxResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nowy box')];
    }
}
