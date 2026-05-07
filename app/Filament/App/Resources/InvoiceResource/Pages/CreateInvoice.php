<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Filament\App\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return InvoiceResource::mutateFormDataBeforeCreate($data);
    }

    protected function afterSave(): void
    {
        // Recompute totals z items po dodaniu pozycji w repeaterze
        $invoice = $this->getRecord();
        foreach ($invoice->items as $item) {
            $item->recomputeAmounts()->save();
        }
        $invoice->load('items')->recomputeTotals()->save();
    }
}
