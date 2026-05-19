<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AddonPurchaseResource\Pages;

use App\Filament\Admin\Resources\AddonPurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAddonPurchases extends ListRecords
{
    protected static string $resource = AddonPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
