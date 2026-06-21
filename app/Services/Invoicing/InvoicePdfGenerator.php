<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Central\Invoice as CentralInvoice;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice as TenantInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generator PDF dla 2 typów faktur:
 *
 *   1. **Tenant invoice** — FV wystawiana przez stable/transporter dla
 *      ich klientów. Branding tenant'a (logo + primary_color z `tenant.branding`),
 *      dane sprzedawcy ze snapshotu na invoice (seller_name/nip/address).
 *
 *   2. **Hovera invoice** — central FV wystawiana przez Hoverę (Sendormeco
 *      Holding sp. z o.o.) tenant'om za subskrypcję SaaS. Branding Hovery
 *      (logo + ochra paleta), dane sprzedawcy z `config('hovera.legal')`.
 *
 * Czemu 2 templaty zamiast 1 z if/else: różne layouty (Hovera FV jest
 * stylistycznie premium-SaaS, tenant FV jest klasyczna B2B), różne źródła
 * danych sprzedawcy. Współdzielony pattern (DomPDF + DejaVu Sans dla
 * polskich znaków) ale layout per typ.
 *
 * Inspirowane wzorcem `TransportInvoicePdfGenerator` (PR transport faza 3).
 */
class InvoicePdfGenerator
{
    public function generateForTenant(TenantInvoice $invoice, ?Tenant $tenant = null): string
    {
        return $this->pdfForTenant($invoice, $tenant)->output();
    }

    public function generateForCentral(CentralInvoice $invoice): string
    {
        return $this->pdfForCentral($invoice)->output();
    }

    /**
     * Render HTML (bez stream'owania) — używane w testach żeby asercje
     * na zawartość były odporne na binarność PDF.
     */
    public function renderTenantHtml(TenantInvoice $invoice, ?Tenant $tenant = null): string
    {
        return view('pdf.tenant-invoice', $this->dataForTenant($invoice, $tenant))->render();
    }

    public function renderCentralHtml(CentralInvoice $invoice): string
    {
        return view('pdf.hovera-invoice', $this->dataForCentral($invoice))->render();
    }

    private function pdfForTenant(TenantInvoice $invoice, ?Tenant $tenant)
    {
        return Pdf::loadView('pdf.tenant-invoice', $this->dataForTenant($invoice, $tenant))
            ->setPaper('a4')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
    }

    private function pdfForCentral(CentralInvoice $invoice)
    {
        return Pdf::loadView('pdf.hovera-invoice', $this->dataForCentral($invoice))
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
    private function dataForTenant(TenantInvoice $invoice, ?Tenant $tenant): array
    {
        $branding = (array) ($tenant?->branding ?? []);

        return [
            'invoice' => $invoice->loadMissing('items'),
            'tenant' => $tenant,
            'branding' => $branding,
            'primary_color' => (string) ($branding['primary_color'] ?? '#A8956B'),
            'logo_url' => $branding['logo_url'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataForCentral(CentralInvoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'seller' => (array) config('hovera.legal'),
            'buyer' => $invoice->tenant,
            'snapshot' => (array) ($invoice->payload_snapshot ?? []),
        ];
    }
}
