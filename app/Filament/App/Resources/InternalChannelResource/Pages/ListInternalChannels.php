<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InternalChannelResource\Pages;

use App\Filament\App\Resources\InternalChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInternalChannels extends ListRecords
{
    protected static string $resource = InternalChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('app/internal_channel.action.new')),
        ];
    }
}
