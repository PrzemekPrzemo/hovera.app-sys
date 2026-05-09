<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\FeedItemResource\Pages;

use App\Filament\App\Resources\FeedItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeedItem extends EditRecord
{
    protected static string $resource = FeedItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
