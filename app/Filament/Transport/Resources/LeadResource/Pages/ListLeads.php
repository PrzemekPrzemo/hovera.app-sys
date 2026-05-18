<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\LeadResource\Pages;

use App\Filament\Transport\Resources\LeadResource;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;
}
