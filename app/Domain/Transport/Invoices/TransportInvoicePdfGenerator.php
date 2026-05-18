<?php

declare(strict_types=1);

namespace App\Domain\Transport\Invoices;

use App\Models\Central\Tenant;
use App\Models\Tenant\TransportInvoice;
use App\Tenancy\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generator PDF faktury transportowej. Branded layout z polskimi znakami
 * (DejaVu Sans). Patrz docs/TRANSPORT.md §9 faza 3 (krok C2).
 */
class TransportInvoicePdfGenerator
{
    public function generate(TransportInvoice $invoice): string
    {
        return $this->pdf($invoice)->output();
    }

    public function render(TransportInvoice $invoice): string
    {
        return view('transport.invoice-pdf', $this->dataFor($invoice))->render();
    }

    private function pdf(TransportInvoice $invoice)
    {
        return Pdf::loadView('transport.invoice-pdf', $this->dataFor($invoice))
            ->setPaper('a4')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(TransportInvoice $invoice): array
    {
        /** @var Tenant|null $tenant */
        $tenant = app(TenantManager::class)->current();

        return [
            'invoice' => $invoice->loadMissing('items'),
            'tenant' => $tenant,
        ];
    }
}
