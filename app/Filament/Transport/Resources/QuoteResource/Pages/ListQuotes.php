<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\QuoteResource\Pages;

use App\Filament\Transport\Resources\QuoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
