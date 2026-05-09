<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TreatmentTemplateResource\Pages;

use App\Filament\App\Resources\TreatmentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreatmentTemplates extends ListRecords
{
    protected static string $resource = TreatmentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
