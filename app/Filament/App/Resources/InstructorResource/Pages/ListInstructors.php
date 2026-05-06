<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InstructorResource\Pages;

use App\Filament\App\Resources\InstructorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstructors extends ListRecords
{
    protected static string $resource = InstructorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
