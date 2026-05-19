<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Publiczny portal klienta dla pojedynczego leada — dostęp przez permanent
 * link `/transport/zapytanie/portal/{slug}` (UUID z `transport_leads.access_slug`).
 *
 * Klient po wypełnieniu `/transport/zapytanie` dostaje email z tym linkiem.
 * Strona pokazuje:
 *   - podsumowanie zapytania
 *   - listę napływających ofert od przewoźników (transport_lead_responses)
 *   - CTA „Załóż konto żeby widzieć historię" (PR-3, na razie placeholder)
 *
 * Brak auth gate — slug to UUID v4, dostęp do dokładnie tego leada. Revoke
 * przez `access_revoked_at` ustawione w admin'ie. Nie pokazujemy listy
 * innych leadów ani danych innych klientów.
 */
class TransportLeadPortalController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $lead = $this->resolveLead($slug);

        // Lazy-load transporter info dla każdej oferty — central'na tabela
        // `transport_lead_responses` ma `transporter_tenant_id`, my chcemy
        // też nazwę firmy żeby klient widział kto wystawia ofertę.
        $responses = TransportLeadResponse::query()
            ->where('lead_id', $lead->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderBy('price_gross')
            ->get();

        $transporters = Tenant::query()
            ->whereIn('id', $responses->pluck('transporter_tenant_id')->unique())
            ->get()
            ->keyBy('id');

        return view('public.transport.lead-portal', [
            'lead' => $lead,
            'responses' => $responses,
            'transporters' => $transporters,
        ]);
    }

    private function resolveLead(string $slug): TransportLead
    {
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $slug)) {
            throw new NotFoundHttpException;
        }

        $lead = TransportLead::query()->where('access_slug', $slug)->first();

        if ($lead === null || ! $lead->isPortalAccessible()) {
            throw new NotFoundHttpException;
        }

        return $lead;
    }
}
