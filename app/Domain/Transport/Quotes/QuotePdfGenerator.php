<?php

declare(strict_types=1);

namespace App\Domain\Transport\Quotes;

use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportSettings;
use App\Tenancy\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generuje PDF oferty transportowej. Branded — używa logo i brand colors
 * tenant'a z `tenants.branding`. Patrz docs/TRANSPORT.md §9 faza 3 punkt 3.
 *
 * Trzy publiczne metody:
 *   - generate(): zwraca binary stream PDF (do downloadu)
 *   - save(): zapisuje do storage tenant'a i ustawia quote.pdf_url
 *   - render(): zwraca HTML (do debug / preview w UI)
 */
class QuotePdfGenerator
{
    public function generate(Quote $quote): string
    {
        return $this->pdf($quote)->output();
    }

    public function render(Quote $quote): string
    {
        $data = $this->dataFor($quote);

        return view('transport.quote-pdf', $data)->render();
    }

    /**
     * Renderuje + zapisuje PDF do storage'u i aktualizuje quote.pdf_url.
     * Path: storage/app/transport/quotes/{quote->id}.pdf
     */
    public function save(Quote $quote): string
    {
        $path = 'transport/quotes/'.$quote->id.'.pdf';
        $disk = config('transport.pdf.disk', 'local');

        \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $this->generate($quote));

        $quote->forceFill(['pdf_url' => $path])->save();

        return $path;
    }

    private function pdf(Quote $quote)
    {
        $data = $this->dataFor($quote);

        return Pdf::loadView('transport.quote-pdf', $data)
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
    private function dataFor(Quote $quote): array
    {
        /** @var Tenant|null $tenant */
        $tenant = app(TenantManager::class)->current();
        $settings = TransportSettings::current();

        return [
            'quote' => $quote,
            'tenant' => $tenant,
            'settings' => $settings,
            'sellerName' => (string) ($tenant?->legal_name ?: $tenant?->name ?: 'Hovera Transport'),
            'sellerTaxId' => $tenant?->tax_id,
            'sellerLogoUrl' => data_get($tenant?->branding, 'logo_url'),
            'sellerEmail' => data_get($tenant?->branding, 'contact_email'),
            'sellerPhone' => data_get($tenant?->branding, 'contact_phone'),
            'sellerAddress' => data_get($tenant?->branding, 'address'),
        ];
    }
}
