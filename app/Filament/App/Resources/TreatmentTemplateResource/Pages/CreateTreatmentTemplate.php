<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TreatmentTemplateResource\Pages;

use App\Filament\App\Resources\TreatmentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTreatmentTemplate extends CreateRecord
{
    protected static string $resource = TreatmentTemplateResource::class;
}
