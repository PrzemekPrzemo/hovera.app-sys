<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SpecialistResource\Pages;

use App\Filament\App\Resources\SpecialistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSpecialist extends CreateRecord
{
    protected static string $resource = SpecialistResource::class;
}
