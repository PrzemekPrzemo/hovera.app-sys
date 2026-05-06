<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\Pages;

use App\Filament\App\Resources\HorseResource;
use Filament\Resources\Pages\EditRecord;

class EditHorse extends EditRecord
{
    protected static string $resource = HorseResource::class;
}
