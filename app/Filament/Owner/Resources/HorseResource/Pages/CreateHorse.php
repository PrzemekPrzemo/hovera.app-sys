<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\HorseResource\Pages;

use App\Filament\Owner\Resources\HorseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHorse extends CreateRecord
{
    protected static string $resource = HorseResource::class;
}
