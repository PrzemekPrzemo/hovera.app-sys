<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\PlanAddon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Public pricing page — `/pricing`. Reads plans from central DB
 * (filtered to is_public=true, is_active=true) and renders Hovera-branded
 * comparison table with monthly/yearly toggle.
 *
 * USP: wszyscy konkurenci PL (Nasza Stajnia, Horstable, Redini, Equita)
 * ukrywają cennik za formularzem kontaktowym. My pokazujemy.
 *
 * Routes:
 *   /pricing               → plany stajenne (legacy widok)
 *   /pricing/transport     → plany transportowe (Start/Pro/Business/Enterprise)
 *                            z toggle waluty (PLN/EUR/GBP/AUD/NZD).
 */
class PricingController extends Controller
{
    public function show(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->forStables()
            ->orderBy('sort_order')
            ->get();

        return view('public.pricing.index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Cennik planów transportowych — marketing spec
     * (hovera.app/produkt/transport/).
     *
     * Currency precedence: query param `?currency=` → locale default
     * (`pl` → PLN, else EUR) → first supported. Akceptuje wyłącznie
     * waluty z `Plan::supportedCurrencies()` żeby uniknąć injectu.
     */
    public function showTransport(Request $request): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->forTransporters()
            ->orderBy('sort_order')
            ->get();

        $supported = Plan::supportedCurrencies();
        $localeDefault = app()->getLocale() === 'pl' ? 'PLN' : 'EUR';

        $requested = strtoupper((string) $request->query('currency', ''));
        $currency = in_array($requested, $supported, true)
            ? $requested
            : (in_array($localeDefault, $supported, true) ? $localeDefault : $supported[0]);

        $addons = PlanAddon::query()
            ->where('is_active', true)
            ->where('is_global', true)
            ->orderBy('sort_order')
            ->get();

        return view('public.pricing.transport', [
            'plans' => $plans,
            'addons' => $addons,
            'currency' => $currency,
            'allCurrencies' => $supported,
            'audience' => TenantType::Transporter->value,
        ]);
    }
}
