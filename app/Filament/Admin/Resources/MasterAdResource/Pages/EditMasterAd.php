<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MasterAdResource\Pages;

use App\Filament\Admin\Resources\MasterAdResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMasterAd extends EditRecord
{
    protected static string $resource = MasterAdResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
