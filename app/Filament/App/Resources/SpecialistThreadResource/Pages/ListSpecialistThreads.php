<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SpecialistThreadResource\Pages;

use App\Filament\App\Resources\SpecialistThreadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSpecialistThreads extends ListRecords
{
    protected static string $resource = SpecialistThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('app/specialist_thread.action.new')),
        ];
    }
}
