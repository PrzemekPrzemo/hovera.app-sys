<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\InvoiceResource;
use App\Models\Tenant\Invoice;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function beforeSave(): void
    {
        // Edytowanie po wystawieniu jest zabronione — UI ma to ukrywać,
        // ale dla pewności blokujemy także na poziomie kontrolera.
        $invoice = $this->getRecord();
        if ($invoice instanceof Invoice && $invoice->status !== InvoiceStatus::Draft) {
            abort(403, 'Nie można edytować wystawionej faktury — wystaw korektę.');
        }
    }

    protected function afterSave(): void
    {
        $invoice = $this->getRecord();
        foreach ($invoice->items as $item) {
            $item->recomputeAmounts()->save();
        }
        $invoice->load('items')->recomputeTotals()->save();
    }
}
