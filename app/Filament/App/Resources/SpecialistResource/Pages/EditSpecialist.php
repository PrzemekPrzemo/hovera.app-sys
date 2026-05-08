<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SpecialistResource\Pages;

use App\Filament\App\Resources\SpecialistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSpecialist extends EditRecord
{
    protected static string $resource = SpecialistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
