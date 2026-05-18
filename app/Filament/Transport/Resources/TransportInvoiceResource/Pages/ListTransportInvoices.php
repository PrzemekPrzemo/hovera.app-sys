<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\TransportInvoiceResource\Pages;

use App\Filament\Transport\Resources\TransportInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListTransportInvoices extends ListRecords
{
    protected static string $resource = TransportInvoiceResource::class;
}
