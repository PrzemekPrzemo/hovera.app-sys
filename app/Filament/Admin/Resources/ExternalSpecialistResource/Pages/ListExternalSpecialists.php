<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExternalSpecialistResource\Pages;

use App\Filament\Admin\Resources\ExternalSpecialistResource;
use Filament\Resources\Pages\ListRecords;

class ListExternalSpecialists extends ListRecords
{
    protected static string $resource = ExternalSpecialistResource::class;
}
