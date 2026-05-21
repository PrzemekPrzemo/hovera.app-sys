<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\HorseOwnerResource\Pages;

use App\Filament\Admin\Resources\HorseOwnerResource;
use Filament\Resources\Pages\EditRecord;

class EditHorseOwner extends EditRecord
{
    protected static string $resource = HorseOwnerResource::class;
}
