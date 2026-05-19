<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * User-facing landing po powrocie klienta z P24 dla quote payment.
 * Real-time status pochodzi z webhooka — tutaj tylko redirect na
 * publiczną stronę quote'a z status flash message.
 *
 * P24 redirectuje tylko via GET, status nie jest signed → wyświetlamy
 * stan z naszej DB (p24_paid_at), nie ufamy parametrom requestu.
 *
 * URL: /transport/p24/return/{tenant_slug}/{quote_id}
 *
 * Patrz docs/TRANSPORT.md §15.5.
 */
class Przelewy24QuoteReturnController extends Controller
{
    public function return(Request $request, string $tenantSlug, string $quoteId): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if ($tenant === null) {
            abort(404);
        }

        // Switch do tenant DB żeby znaleźć quote
        app(TenantManager::class)->use($tenant);

        $quote = Quote::query()->find($quoteId);
        if ($quote === null) {
            return redirect('/')->with('status', __('transport/p24.return.unknown'));
        }

        // Jeśli mamy oryginalny accept_token, redirectujemy do landing page'a
        // quote'a z flash. Inaczej generic homepage.
        if ($quote->accept_token) {
            $landingUrl = route('public.transport.quote', [
                'slug' => $tenantSlug,
                'token' => $quote->accept_token,
            ]);

            $key = $quote->p24_paid_at !== null
                ? 'transport/p24.return.paid'
                : 'transport/p24.return.pending';

            return redirect($landingUrl)->with('status', __($key, ['number' => $quote->number]));
        }

        return redirect('/')->with(
            'status',
            __($quote->p24_paid_at !== null ? 'transport/p24.return.paid' : 'transport/p24.return.pending', [
                'number' => $quote->number,
            ]),
        );
    }
}
