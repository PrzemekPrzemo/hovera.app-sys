<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\BoardingServiceResource\Pages;

use App\Filament\App\Resources\BoardingServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoardingService extends EditRecord
{
    protected static string $resource = BoardingServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
