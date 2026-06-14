<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\BoxInquiryResource\Pages;

use App\Filament\App\Resources\BoxInquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListBoxInquiries extends ListRecords
{
    protected static string $resource = BoxInquiryResource::class;

    // Brak CreateAction — zapytania powstają tylko przez publiczny formularz.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
