<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\FeedItemResource\Pages;

use App\Filament\App\Resources\FeedItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedItem extends CreateRecord
{
    protected static string $resource = FeedItemResource::class;
}
