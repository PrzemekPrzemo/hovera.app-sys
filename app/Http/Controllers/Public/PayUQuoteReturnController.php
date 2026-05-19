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
 * User-facing landing po powrocie klienta z PayU dla quote payment.
 * Real-time status pochodzi z webhooka — tutaj tylko redirect na publiczną
 * stronę quote'a z flash message.
 *
 * PayU redirectuje via GET na continueUrl — status NIE jest signed →
 * wyświetlamy stan z naszej DB (`payu_paid_at`), nie ufamy parametrom
 * requestu.
 *
 * URL: /transport/payu/return/{tenant_slug}/{quote_id}
 *
 * Analogous do `Przelewy24QuoteReturnController`. Patrz docs/TRANSPORT.md §16.
 */
class PayUQuoteReturnController extends Controller
{
    public function return(Request $request, string $tenantSlug, string $quoteId): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if ($tenant === null) {
            abort(404);
        }

        // Switch do tenant DB żeby querować Quote.
        app(TenantManager::class)->use($tenant);

        $quote = Quote::query()->find($quoteId);
        if ($quote === null) {
            return redirect('/')->with('status', __('transport/payu.return.unknown'));
        }

        if ($quote->accept_token) {
            $landingUrl = route('public.transport.quote', [
                'slug' => $tenantSlug,
                'token' => $quote->accept_token,
            ]);

            $key = $quote->payu_paid_at !== null
                ? 'transport/payu.return.paid'
                : 'transport/payu.return.pending';

            return redirect($landingUrl)->with('status', __($key, ['number' => $quote->number]));
        }

        return redirect('/')->with(
            'status',
            __($quote->payu_paid_at !== null ? 'transport/payu.return.paid' : 'transport/payu.return.pending', [
                'number' => $quote->number,
            ]),
        );
    }
}
