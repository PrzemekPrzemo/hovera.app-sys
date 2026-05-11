<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MasterAdResource\Pages;

use App\Filament\Admin\Resources\MasterAdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMasterAds extends ListRecords
{
    protected static string $resource = MasterAdResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
