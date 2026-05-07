<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Tenant\Invoice;
use Illuminate\Support\Facades\URL;

/**
 * Tworzy publiczny URL do faktury — z signed signature, ważny do
 * 90 dni po wystawieniu (lub 14 dni po due_at — co dłuższe).
 *
 * Klient klika link w mailu → widzi fakturę + przycisk "Zapłać teraz".
 * Bezpieczeństwo: signature musi być valid + invoice ID musi się
 * zgadzać. Brute force chroni Laravel rate limiter na rute.
 */
class InvoicePublicLink
{
    public function for(Invoice $invoice, string $tenantSlug): string
    {
        $expiresAt = $this->expiryFor($invoice);

        return URL::temporarySignedRoute(
            name: 'public.invoice.show',
            expiration: $expiresAt,
            parameters: [
                'slug' => $tenantSlug,
                'invoice' => $invoice->id,
            ],
        );
    }

    private function expiryFor(Invoice $invoice): \DateTimeInterface
    {
        $base = $invoice->issued_at ?? now();
        $standard = (clone $base)->modify('+90 days');

        // Jeśli due_at po 90 dniach (np. 12-miesięczna płatność), zostaw
        // link aktywny do 14 dni po terminie żeby klient mógł zapłacić.
        if ($invoice->due_at) {
            $afterDue = $invoice->due_at->copy()->addDays(14);
            if ($afterDue->greaterThan($standard)) {
                return $afterDue;
            }
        }

        return $standard;
    }
}
